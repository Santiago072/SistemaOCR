import io
import os
import cv2
import fitz
import zxingcpp
import numpy as np
from typing import List, Optional, Dict, Any
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse

from app.pdf417_decoder import parse_colombian_pdf417_data
from app.text_ocr import extract_text_from_id

app = FastAPI(
    title="Microservicio de OCR y Extracción de Documentos SENA",
    version="1.0.0",
    description="Microservicio de alto rendimiento para decodificación PDF417 y OCR neuronal sobre documentos de identidad colombianos."
)

def scan_image_for_barcode(img_bgr: np.ndarray) -> Optional[dict]:
    if img_bgr is None or img_bgr.size == 0:
        return None

    h, w = img_bgr.shape[:2]
    scales = [1.0]
    if max(h, w) < 800:
        scales.append(1.5)

    for scale in scales:
        resized = cv2.resize(img_bgr, (0, 0), fx=scale, fy=scale) if scale != 1.0 else img_bgr
        gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY) if len(resized.shape) == 3 else resized

        rotaciones = [
            (None, "0_grados"),
            (cv2.ROTATE_180, "180_grados"),
            (cv2.ROTATE_90_CLOCKWISE, "90_grados"),
            (cv2.ROTATE_90_COUNTERCLOCKWISE, "270_grados")
        ]

        for rot_code, label in rotaciones:
            scan_img = cv2.rotate(gray, rot_code) if rot_code is not None else gray
            try:
                results = zxingcpp.read_barcodes(scan_img)
                if results:
                    for b in results:
                        raw_bytes = b.bytes if (hasattr(b, 'bytes') and b.bytes) else b.text.encode('latin1')
                        parsed = parse_colombian_pdf417_data(raw_bytes)
                        if parsed and parsed.get("numero_documento"):
                            parsed["raw_data_json"] = {"orientacion_detectada": label, "formato": str(b.format)}
                            return parsed
            except Exception:
                pass

    return None

def process_page_in_memory(doc, page_num: int) -> dict:
    page_idx = page_num - 1
    page = doc.load_page(page_idx)

    # Renderizado a 200 DPI para precisión óptima
    zoom = 200 / 72
    mat = fitz.Matrix(zoom, zoom)
    pix = page.get_pixmap(matrix=mat, alpha=False)
    img_np = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, 3)
    img_page_bgr = cv2.cvtColor(img_np, cv2.COLOR_RGB2BGR)

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
        "ruta_imagen_recorte": f"page_{page_num}.jpg",
        "raw_data_json": {}
    }

    # 1. Buscar en imágenes incrustadas de alta resolución
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

    # 2. Fallback: Buscar en página renderizada
    parsed_page = scan_image_for_barcode(img_page_bgr)
    if parsed_page:
        result.update(parsed_page)
        return result

    # 3. Fallback OCR Visual (RapidOCR ONNX)
    try:
        ocr_res = extract_text_from_id(img_page_bgr)
        if ocr_res and ocr_res.get("numero_documento"):
            result.update(ocr_res)
            return result
    except Exception as e:
        result["raw_data_json"]["ocr_error"] = str(e)

    return result

@app.get("/health")
def health_check():
    return {"status": "ok", "service": "ocr-service"}

@app.post("/extract")
async def extract_pdf_data(file: UploadFile = File(...)):
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="El archivo enviado debe ser formato PDF.")

    contents = await file.read()
    if not contents:
        raise HTTPException(status_code=400, detail="El archivo PDF está vacío.")

    try:
        doc = fitz.open(stream=contents, filetype="pdf")
        total_pages = len(doc)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Error al abrir el documento PDF: {str(e)}")

    if total_pages == 0:
        return JSONResponse(content={
            "status": "success",
            "total_paginas": 0,
            "total_personas": 0,
            "paginas": []
        })

    paginas_resultado = []
    for p in range(1, total_pages + 1):
        res = process_page_in_memory(doc, p)
        paginas_resultado.append(res)

    doc.close()

    return {
        "status": "success",
        "total_paginas": total_pages,
        "total_personas": len(paginas_resultado),
        "paginas": paginas_resultado
    }
