"""Convierte el archivo que suba el usuario en paginas con texto reconocido.

Acepta PDF (cada pagina es una imagen) o imagenes sueltas (jpg, png, etc.).
Guarda una version liviana de cada pagina para mostrarla en pantalla y le pasa
OCR a una version en mayor resolucion.
"""
import os
import threading

DPI_OCR = 140        # Resolución calibrada para capturar micro-texto y números nítidos
DPI_VISTA = 100      # liviano para el navegador
LADO_MAX_OCR = 1500  # resolución óptima para no perder detalles finos

EXT_IMAGEN = {".jpg", ".jpeg", ".png", ".bmp", ".tif", ".tiff", ".webp"}
EXT_PDF = {".pdf"}
EXT_ACEPTADAS = EXT_IMAGEN | EXT_PDF

_motor = None
_candado_motor = threading.Lock()


def motor():
    """Carga el OCR una sola vez; la primera llamada baja los modelos."""
    global _motor
    if _motor is None:
        with _candado_motor:
            if _motor is None:
                from rapidocr_onnxruntime import RapidOCR
                _motor = RapidOCR(use_angle_cls=False)
    return _motor


def es_archivo_aceptado(nombre):
    return os.path.splitext(nombre)[1].lower() in EXT_ACEPTADAS


def _a_bgr(pix):
    """Pixmap de PyMuPDF -> arreglo BGR."""
    import numpy as np
    arr = np.frombuffer(pix.samples, dtype=np.uint8).reshape(pix.height, pix.width, pix.n)
    if pix.n == 4:
        arr = arr[:, :, :3]
    return np.ascontiguousarray(arr[:, :, ::-1])


def _lineas(imagen):
    """Pasa OCR a una imagen y devuelve los renglones con su posicion.
    Aplica un recorte dinámico de márgenes blancos para reducir el área de inferencia."""
    import numpy as np
    h_orig, w_orig = imagen.shape[:2]

    # Detectar bounding box del contenido no-blanco (píxeles con brillo < 248)
    if len(imagen.shape) == 3:
        gris = (0.299 * imagen[:, :, 2] + 0.587 * imagen[:, :, 1] + 0.114 * imagen[:, :, 0]).astype(np.uint8)
    else:
        gris = imagen

    no_blanco = np.where(gris < 248)
    offset_x, offset_y = 0, 0
    img_ocr = imagen

    if len(no_blanco[0]) > 0 and len(no_blanco[1]) > 0:
        y_min, y_max = int(np.min(no_blanco[0])), int(np.max(no_blanco[0]))
        x_min, x_max = int(np.min(no_blanco[1])), int(np.max(no_blanco[1]))

        # Agregar un margen de seguridad de 20px
        pad = 20
        y_min = max(0, y_min - pad)
        y_max = min(h_orig, y_max + pad)
        x_min = max(0, x_min - pad)
        x_max = min(w_orig, x_max + pad)

        if (x_max - x_min) >= 100 and (y_max - y_min) >= 100:
            img_ocr = imagen[y_min:y_max, x_min:x_max]
            offset_x = x_min
            offset_y = y_min

    res, _ = motor()(img_ocr)

    # Reintento con realce adaptativo (CLAHE) SOLO si la página arrojó muy pocas líneas (< 3).
    # Esto rescata escaneos sobreexpuestos/lavados sin penalizar el tiempo del 95%+ de páginas normales.
    if not res or len(res) < 3:
        import cv2
        if len(img_ocr.shape) == 3:
            lab = cv2.cvtColor(img_ocr, cv2.COLOR_BGR2LAB)
            l_chan, a_chan, b_chan = cv2.split(lab)
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
            cl = clahe.apply(l_chan)
            img_realzada = cv2.cvtColor(cv2.merge((cl, a_chan, b_chan)), cv2.COLOR_LAB2BGR)
        else:
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
            img_realzada = clahe.apply(img_ocr)

        res_reintento, _ = motor()(img_realzada)
        if res_reintento and len(res_reintento) > (len(res) if res else 0):
            res = res_reintento

    lineas = []
    for box, texto, score in (res or []):
        xs = [p[0] + offset_x for p in box]
        ys = [p[1] + offset_y for p in box]
        lineas.append({
            "texto": texto,
            "conf": round(float(score), 3),
            "x": round(min(xs)), "y": round(min(ys)),
            "w": round(max(xs) - min(xs)), "h": round(max(ys) - min(ys)),
        })
    return lineas


def _guardar_vista(pix, destino):
    """Guarda la version liviana que se ve en pantalla de forma eficiente."""
    from PIL import Image
    img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
    img.save(destino, quality=70)


def _procesar_una_pagina_pdf(ruta, i, carpeta_img):
    import fitz
    doc = fitz.open(ruta)
    page = doc[i]
    n = i + 1
    grande = page.get_pixmap(dpi=DPI_OCR)
    nombre = f"pag{n:03}.jpg"
    _guardar_vista(grande, os.path.join(carpeta_img, nombre))
    arr = _a_bgr(grande)
    lineas = _lineas(arr)
    w, h = grande.width, grande.height
    doc.close()
    return {"pagina": n, "imagen": nombre, "ancho": w, "alto": h, "lineas": lineas}


def _paginas_pdf(ruta, carpeta_img, avisar, por_pagina=None):
    import fitz
    from concurrent.futures import ThreadPoolExecutor
    doc = fitz.open(ruta)
    total = doc.page_count
    doc.close()

    # En i7-6600U con 4 procesadores lógicos, 3 workers rinden al máximo
    max_workers = min(3, total) if total > 1 else 1

    with ThreadPoolExecutor(max_workers=max_workers) as executor:
        futures = [executor.submit(_procesar_una_pagina_pdf, ruta, i, carpeta_img) for i in range(total)]
        for i, fut in enumerate(futures):
            p = fut.result()
            if avisar:
                avisar(i + 1, total)
            if por_pagina:
                por_pagina(p)
            yield p


def _paginas_imagenes(rutas, carpeta_img, avisar):
    from PIL import Image
    import numpy as np
    total = len(rutas)
    for i, ruta in enumerate(rutas, 1):
        if avisar:
            avisar(i, total)
        img = Image.open(ruta).convert("RGB")
        nombre = f"pag{i:03}.jpg"
        vista = img.copy()
        vista.thumbnail((1200, 1200))
        vista.save(os.path.join(carpeta_img, nombre), quality=70)

        lectura = img
        if max(img.size) > LADO_MAX_OCR:
            lectura = img.copy()
            lectura.thumbnail((LADO_MAX_OCR, LADO_MAX_OCR))

        arreglo = np.ascontiguousarray(np.asarray(lectura)[:, :, ::-1])
        yield {"pagina": i, "imagen": nombre, "ancho": lectura.width,
               "alto": lectura.height, "lineas": _lineas(arreglo)}


def leer_documentos(rutas, carpeta_img, avisar=None, por_pagina=None):
    """Devuelve la lista de paginas con su texto reconocido."""
    os.makedirs(carpeta_img, exist_ok=True)
    if isinstance(rutas, str):
        rutas = [rutas]

    pdfs = [r for r in rutas if os.path.splitext(r)[1].lower() in EXT_PDF]
    imgs = [r for r in rutas if os.path.splitext(r)[1].lower() in EXT_IMAGEN]

    if pdfs and imgs:
        raise ValueError("Sube un PDF o imagenes, pero no los dos al tiempo.")
    if pdfs:
        if len(pdfs) > 1:
            raise ValueError("Sube un solo PDF a la vez.")
        fuente = _paginas_pdf(pdfs[0], carpeta_img, avisar, por_pagina)
    elif imgs:
        fuente = _paginas_imagenes(sorted(imgs), carpeta_img, avisar)
    else:
        raise ValueError("No reconoci el archivo. Sube un PDF o imagenes (jpg, png).")

    paginas = []
    for p in fuente:
        paginas.append(p)
        if por_pagina and not pdfs: # si es imagen se llama aqui, si es pdf ya se llamó adentro
            por_pagina(p)
    return paginas
