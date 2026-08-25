"""
Procesador Principal de PDF y Extractor OCR / PDF417
Recibe la ruta del PDF, procesa cada página y genera un JSON con los participantes detectados.

Uso:
    python extractor.py --pdf "ruta/al/documento.pdf" --output-dir "uploads/recortes"
"""

import os
import sys
import json
import argparse
from typing import Dict, List, Optional, Any
import pymupdf as fitz  # PyMuPDF estándar
import cv2
import numpy as np
import zxingcpp
from PIL import Image

# Forzar stdout en UTF-8 para evitar errores de codificación en Windows
if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')
# Asegurar resolución de módulos en el directorio de python_ocr
current_dir = os.path.dirname(os.path.abspath(__file__))
if current_dir not in sys.path:
    sys.path.insert(0, current_dir)

# Importar decodificadores locales
from pdf417_decoder import parse_colombian_pdf417_data
from text_ocr import extract_text_from_id

def scan_image_for_barcode(img_bgr: np.ndarray) -> Optional[dict]:
    """
    Escanea una imagen BGR buscando códigos de barras PDF417 de cédulas colombianas
    usando decodificación C++ nativa ultra-rápida.
    """
    if img_bgr is None or img_bgr.size == 0:
        return None

    gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY) if len(img_bgr.shape) == 3 else img_bgr

    try:
        # zxingcpp busca PDF417 en cualquier rotación nativamente a nivel C++
        barcodes = zxingcpp.read_barcodes(gray, formats=zxingcpp.BarcodeFormat.PDF417, try_rotate=True, try_downscale=True)
        for b in barcodes:
            raw_bytes = b.bytes if (hasattr(b, 'bytes') and b.bytes) else b.text.encode('latin1')
            parsed = parse_colombian_pdf417_data(raw_bytes)
            if parsed and parsed.get("numero_documento"):
                parsed["raw_data_json"] = {"formato": str(b.format)}
                return parsed
    except Exception:
        pass

    return None

def process_page(doc, page_num: int, output_dir: str) -> dict:
    """
    Procesa una página del PDF:
    1. Renderiza la página a JPEG (200 DPI) para guardarla como vista previa web completa (ambas caras).
    2. Primero busca el código de barras en las imágenes incrustadas de alta resolución originales.
    3. Si no lo encuentra, busca en la página renderizada completa.
    """
    page_idx = page_num - 1
    page = doc.load_page(page_idx)

    # 1. Renderizar imagen de la página completa para el visor web
    zoom = 200 / 72  # 200 DPI: Excelente resolución y peso ligero
    mat = fitz.Matrix(zoom, zoom)
    pix = page.get_pixmap(matrix=mat, alpha=False)
    img_np = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, 3)
    img_page_bgr = cv2.cvtColor(img_np, cv2.COLOR_RGB2BGR)

    os.makedirs(output_dir, exist_ok=True)
    img_filename = f"page_{page_num}.jpg"
    img_path = os.path.join(output_dir, img_filename)
    cv2.imwrite(img_path, img_page_bgr, [cv2.IMWRITE_JPEG_QUALITY, 85])

    result = {
        "numero_pagina": page_num,
        "exito": False,
        "tipo_documento": "CC",
        "numero_documento": None,
        "primer_apellido": None,
        "segundo_apellido": None,
        "primer_nombre": None,
        "segundo_nombre": None,
        "nombre_completo_ocr": None,
        "genero": None,
        "fecha_nacimiento": None,
        "rh": None,
        "metodo_extraccion": "FALLIDO",
        "confianza_score": 0.0,
        "ruta_imagen_recorte": img_filename,
        "raw_data_json": {}
    }

    # 2. Buscar en imágenes incrustadas directas
    imgs = page.get_images()
    for i_info in imgs:
        try:
            img_bytes = doc.extract_image(i_info[0])['image']
            img_cv = cv2.imdecode(np.frombuffer(img_bytes, np.uint8), cv2.IMREAD_COLOR)
            if img_cv is not None:
                parsed = scan_image_for_barcode(img_cv)
                if parsed:
                    result.update(parsed)
                    return result
        except Exception:
            pass

    # 3. Fallback: Buscar en la página renderizada completa
    parsed_page = scan_image_for_barcode(img_page_bgr)
    if parsed_page:
        result.update(parsed_page)
        return result

    # 4. Fallback OCR Visual (Red Neuronal RapidOCR): Procesa cédulas digitales (NUIP / MRZ) o sin código
    try:
        ocr_res = extract_text_from_id(img_page_bgr)
        if ocr_res and ocr_res.get("numero_documento"):
            result.update(ocr_res)
            return result
    except Exception as e:
        result["raw_data_json"]["ocr_error"] = str(e)

    return result

def _process_single_page_worker(args_tuple):
    """
    Worker a nivel de módulo para ejecución en paralelo por procesos.
    Abre su propia instancia del documento de forma segura.
    """
    pdf_path, page_num, output_dir = args_tuple
    try:
        doc = fitz.open(pdf_path)
        res = process_page(doc, page_num, output_dir)
        doc.close()
        return res
    except Exception as e:
        return {
            "numero_pagina": page_num,
            "exito": False,
            "metodo_extraccion": "FALLIDO",
            "confianza_score": 0.0,
            "ruta_imagen_recorte": f"page_{page_num}.jpg",
            "raw_data_json": {"error": str(e)}
        }

def process_pdf(pdf_path: str, output_dir: str) -> dict:
    """
    Procesa todas las páginas del PDF utilizando paralelismo multinúcleo.
    1 página = 1 participante (ambas caras de la cédula).
    """
    if not os.path.exists(pdf_path):
        return {
            "status": "error",
            "message": f"Archivo PDF no encontrado: {pdf_path}",
            "paginas": []
        }

    doc = fitz.open(pdf_path)
    total_pages = len(doc)
    doc.close()

    if total_pages == 0:
        return {
            "status": "success",
            "total_paginas": 0,
            "total_personas": 0,
            "paginas": []
        }

    os.makedirs(output_dir, exist_ok=True)

    # Determinar número óptimo de workers basado en CPU (usar hasta 3 o 4 procesos en paralelo)
    cpu_cores = os.cpu_count() or 2
    workers = min(max(1, cpu_cores - 1), 4)

    tasks = [(pdf_path, p + 1, output_dir) for p in range(total_pages)]
    paginas_resultado = [None] * total_pages

    from concurrent.futures import ProcessPoolExecutor, as_completed

    completed_count = 0
    with ProcessPoolExecutor(max_workers=workers) as executor:
        future_to_page = {executor.submit(_process_single_page_worker, t): t[1] for t in tasks}
        for future in as_completed(future_to_page):
            page_num = future_to_page[future]
            try:
                res = future.result()
                paginas_resultado[page_num - 1] = res
            except Exception as e:
                paginas_resultado[page_num - 1] = {
                    "numero_pagina": page_num,
                    "exito": False,
                    "metodo_extraccion": "FALLIDO",
                    "confianza_score": 0.0,
                    "ruta_imagen_recorte": f"page_{page_num}.jpg",
                    "raw_data_json": {"error": str(e)}
                }
            completed_count += 1
            sys.stderr.write(f"PROGRESS:{completed_count}:{total_pages}\n")
            sys.stderr.flush()

    return {
        "status": "success",
        "total_paginas": total_pages,
        "total_personas": len(paginas_resultado),
        "paginas": paginas_resultado
    }

def main():
    parser = argparse.ArgumentParser(description="Procesador OCR / PDF417 para Fichas de Inscripción")
    parser.add_argument("--pdf", required=True, help="Ruta al archivo PDF")
    parser.add_argument("--output-dir", required=True, help="Directorio para guardar recortes/imágenes de página")
    args = parser.parse_args()

    resultado = process_pdf(args.pdf, args.output_dir)
    print(json.dumps(resultado, ensure_ascii=False, indent=2))

if __name__ == "__main__":
    main()


