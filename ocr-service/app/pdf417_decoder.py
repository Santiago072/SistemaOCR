import re
from typing import Dict, List, Optional, Any

STOP_WORDS = {
    'CC', 'TI', 'CE', 'PEP', 'PPT', 'PAS', 'REPUBLICA', 'COLOMBIA',
    'CEDULA', 'CIUDADANIA', 'IDENTIFICACION', 'PERSONAL', 'REGISTRADOR',
    'NACIONAL', 'ESTADO', 'CIVIL', 'DE', 'LA', 'DEL', 'LOS', 'LAS',
    'CON', 'PUBDSK', 'PUBDSK_1', 'NUIP',
}

def _parsear_campo_fecha_genero(campo: str):
    m = re.match(r'^[01]?([MF])(\d{4})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])', campo.strip())
    if m:
        genero = m.group(1)
        fecha_nac = f"{m.group(2)}-{m.group(3)}-{m.group(4)}"
        rh_m = re.search(r'\b([ABO]{1,2}[+-]|O[+-])\b', campo)
        rh = rh_m.group(1) if rh_m else None
        return genero, fecha_nac, rh
    return None, None, None

def parse_colombian_pdf417_data(raw_data) -> Optional[Dict[str, Any]]:
    if not raw_data:
        return None

    if isinstance(raw_data, str):
        raw_bytes = raw_data.encode('latin1', errors='ignore')
    else:
        raw_bytes = bytes(raw_data)

    if len(raw_bytes) < 10:
        return None

    campos_bytes = raw_bytes.split(b'\x00')
    campos = []
    for c in campos_bytes:
        txt = c.decode('latin1', errors='ignore').strip()
        if txt:
            campos.append(txt)

    if not campos:
        return None

    numero_doc = None
    primer_apellido = None
    idx_alfa = -1

    for i, c in enumerate(campos):
        c_upper = c.upper()
        if 'PUBDSK' in c_upper:
            continue

        m = re.search(r'(\d{6,18})([A-ZÁÉÍÓÚÜÑ]{2,})$', c_upper)
        if m:
            num_str = m.group(1)
            primer_apellido = m.group(2)
            idx_alfa = i

            if len(num_str) >= 10:
                numero_doc = num_str[-10:].lstrip('0')
            elif len(num_str) >= 8:
                numero_doc = num_str[-8:].lstrip('0')
            else:
                numero_doc = num_str.lstrip('0')

            if len(numero_doc) < 6:
                numero_doc = None
            else:
                break

    if not numero_doc:
        first_name_idx = -1
        for i, c in enumerate(campos):
            if re.match(r'^[A-ZÁÉÍÓÚÜÑ]{3,30}$', c.upper().strip()) and c.upper().strip() not in STOP_WORDS and 'PUBDSK' not in c.upper():
                first_name_idx = i
                primer_apellido = c.upper().strip()
                break

        if first_name_idx > 0:
            for k in range(first_name_idx - 1, -1, -1):
                c_clean = campos[k].strip()
                if re.match(r'^\d{6,10}$', c_clean):
                    numero_doc = c_clean.lstrip('0')
                    idx_alfa = first_name_idx
                    break

    if not numero_doc:
        for i, c in enumerate(campos):
            c_clean = c.strip()
            if re.match(r'^\d{7,10}$', c_clean):
                numero_doc = c_clean.lstrip('0')
                idx_alfa = i
                break

    if not numero_doc or len(numero_doc) < 7:
        return None

    nombres_extraidos: List[str] = []
    if primer_apellido and primer_apellido not in STOP_WORDS:
        nombres_extraidos.append(primer_apellido)

    genero = None
    fecha_nac = None
    rh = None

    start_idx = (idx_alfa + 1) if idx_alfa != -1 else 0
    for c in campos[start_idx:]:
        c_strip = c.strip()
        g, f, r = _parsear_campo_fecha_genero(c_strip)
        if g:
            genero, fecha_nac, rh = g, f, r
            break

        if re.match(r'^[A-ZÁÉÍÓÚÜÑ\s]{2,30}$', c_strip.upper()):
            nombre_limpio = c_strip.upper().strip()
            if nombre_limpio not in STOP_WORDS and len(nombre_limpio) >= 2:
                if nombre_limpio not in nombres_extraidos:
                    nombres_extraidos.append(nombre_limpio)
        else:
            if len(nombres_extraidos) >= 2:
                break

    ap1 = nombres_extraidos[0] if len(nombres_extraidos) > 0 else ""
    ap2 = nombres_extraidos[1] if len(nombres_extraidos) > 1 else ""
    nom1 = nombres_extraidos[2] if len(nombres_extraidos) > 2 else ""
    nom2 = " ".join(nombres_extraidos[3:]) if len(nombres_extraidos) > 3 else ""

    if len(nombres_extraidos) == 2:
        nom1 = nombres_extraidos[1]
        ap2 = ""

    nombre_completo = " ".join(filter(None, [nom1, nom2, ap1, ap2])).strip()
    if not nombre_completo:
        return None

    return {
        "exito": True,
        "tipo_documento": "CC",
        "numero_documento": numero_doc,
        "primer_apellido": ap1,
        "segundo_apellido": ap2,
        "primer_nombre": nom1,
        "segundo_nombre": nom2,
        "nombre_completo_ocr": nombre_completo,
        "genero": genero,
        "fecha_nacimiento": fecha_nac,
        "rh": rh,
        "metodo_extraccion": "PDF417",
        "confianza_score": 100.00
    }
