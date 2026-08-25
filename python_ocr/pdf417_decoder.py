"""
Decodificador del Código de Barras PDF417 de la Registraduría Nacional de Colombia.

El código PDF417 de la cédula colombiana codifica campos delimitados por el byte nulo 0x00.
Estructura estándar de campos en cédula colombiana:
  [0] Número de control interno (ej: '0330392811')
  [1..n] 'PubDSK_1' / identificadores de la registraduría
  [k] <prefijo><NUMERO_CEDULA><PRIMER_APELLIDO> (ej: '808047761117489876PARRA' o '0079665698URREA')
  [k+1] SEGUNDO APELLIDO (ej: 'HERNANDEZ')
  [k+2] PRIMER NOMBRE (ej: 'DIEGO')
  [k+3] SEGUNDO NOMBRE (ej: 'ARMANDO') (opcional)
  [k+4] Datos biométricos: 0M19860626...O+ (género, fecha nacimiento, RH)
  [k+5..] Bloque de huella dactilar/minucias biométricas en bytes binarios (NO es texto)
"""

import re
from typing import Dict, List, Optional, Any


STOP_WORDS = {
    'CC', 'TI', 'CE', 'PEP', 'PPT', 'PAS', 'REPUBLICA', 'COLOMBIA',
    'CEDULA', 'CIUDADANIA', 'IDENTIFICACION', 'PERSONAL', 'REGISTRADOR',
    'NACIONAL', 'ESTADO', 'CIVIL', 'DE', 'LA', 'DEL', 'LOS', 'LAS',
    'CON', 'PUBDSK', 'PUBDSK_1', 'NUIP',
}


def _parsear_campo_fecha_genero(campo: str):
    """
    Parsea el campo de datos biométricos del PDF417 colombiano.
    Formato típico: '0M19860626...' o '1F19901115...'
    Retorna (genero, fecha_nacimiento, rh)
    """
    m = re.match(r'^[01]?([MF])(\d{4})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])', campo.strip())
    if m:
        genero = m.group(1)
        fecha_nac = f"{m.group(2)}-{m.group(3)}-{m.group(4)}"
        rh_m = re.search(r'\b([ABO]{1,2}[+-]|O[+-])\b', campo)
        rh = rh_m.group(1) if rh_m else None
        return genero, fecha_nac, rh
    return None, None, None


def parse_colombian_pdf417_data(raw_data) -> Optional[Dict[str, Any]]:
    """
    Parsea los datos del código de barras PDF417 de una cédula colombiana.
    Acepta tanto `bytes` como `str`.
    """
    if not raw_data:
        return None

    # Convertir a bytes si es string latin1
    if isinstance(raw_data, str):
        raw_bytes = raw_data.encode('latin1', errors='ignore')
    else:
        raw_bytes = bytes(raw_data)

    if len(raw_bytes) < 10:
        return None

    # Dividir estrictamente por byte nulo \x00
    campos_bytes = raw_bytes.split(b'\x00')
    campos = []
    for c in campos_bytes:
        # Decodificar texto ignorando caracteres no imprimibles
        txt = c.decode('latin1', errors='ignore').strip()
        if txt:
            campos.append(txt)

    if not campos:
        return None

    numero_doc = None
    primer_apellido = None
    idx_alfa = -1

    # 1. Localizar el campo alfanumérico principal: <digitos><NUMERO_CEDULA><PRIMER_APELLIDO>
    # o si viene separado por campos (ej: 'PubDSK_1', '457694', '1115946894', 'ESCALANTE')
    for i, c in enumerate(campos):
        c_upper = c.upper()
        if 'PUBDSK' in c_upper:
            continue

        # Buscar dígitos seguidos de letras al final (ej. 808047761117489876PARRA)
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
            break

    # Si no se encontró el campo concatenado, buscar el número adyacente al primer apellido
    if not numero_doc:
        # Encontrar el índice del primer apellido
        first_name_idx = -1
        for i, c in enumerate(campos):
            if re.match(r'^[A-ZÁÉÍÓÚÜÑ]{3,30}$', c.upper().strip()) and c.upper().strip() not in STOP_WORDS and 'PUBDSK' not in c.upper():
                first_name_idx = i
                primer_apellido = c.upper().strip()
                break

        if first_name_idx > 0:
            # El número de cédula/TI está justo antes del primer apellido (ej: '1115946894', 'ESCALANTE')
            for k in range(first_name_idx - 1, -1, -1):
                c_clean = campos[k].strip()
                if re.match(r'^\d{6,10}$', c_clean):
                    numero_doc = c_clean.lstrip('0')
                    idx_alfa = first_name_idx
                    break

    # Fallback general si aún no se encontró
    if not numero_doc:
        for i, c in enumerate(campos):
            c_clean = c.strip()
            if re.match(r'^\d{7,10}$', c_clean):
                numero_doc = c_clean.lstrip('0')
                idx_alfa = i
                break

    if not numero_doc:
        return None

    # 2. Extraer apellidos y nombres de los campos subsiguientes
    # Los nombres aparecen inmediatamente después de idx_alfa y ANTES del campo biométrico
    nombres_extraidos: List[str] = []
    if primer_apellido and primer_apellido not in STOP_WORDS:
        nombres_extraidos.append(primer_apellido)

    genero = None
    fecha_nac = None
    rh = None

    start_idx = (idx_alfa + 1) if idx_alfa != -1 else 0
    for c in campos[start_idx:]:
        c_strip = c.strip()

        # Si llegamos al campo biométrico de fecha/género, lo extraemos y DETENEMOS la lectura de nombres
        # (para no leer los bytes binarios de la huella dactilar que causan caracteres extraños)
        g, f, r = _parsear_campo_fecha_genero(c_strip)
        if g:
            genero, fecha_nac, rh = g, f, r
            break

        # Si el campo tiene solo letras mayúsculas válidas de nombre
        if re.match(r'^[A-ZÁÉÍÓÚÜÑ\s]{2,30}$', c_strip.upper()):
            nombre_limpio = c_strip.upper().strip()
            if nombre_limpio not in STOP_WORDS and len(nombre_limpio) >= 2:
                if nombre_limpio not in nombres_extraidos:
                    nombres_extraidos.append(nombre_limpio)
        else:
            # Si contiene caracteres no alfabéticos raros (inicio del bloque biométrico), parar
            if len(nombres_extraidos) >= 2:
                break

    # 3. Asignar nombres en el orden legal colombiano: AP1, AP2, NOM1, NOM2
    ap1 = nombres_extraidos[0] if len(nombres_extraidos) > 0 else ""
    ap2 = nombres_extraidos[1] if len(nombres_extraidos) > 1 else ""
    nom1 = nombres_extraidos[2] if len(nombres_extraidos) > 2 else ""
    nom2 = " ".join(nombres_extraidos[3:]) if len(nombres_extraidos) > 3 else ""

    # Si solo hay 2 palabras (ej: AP1, NOM1)
    if len(nombres_extraidos) == 2:
        nom1 = nombres_extraidos[1]
        ap2 = ""

    nombre_completo = " ".join(filter(None, [nom1, nom2, ap1, ap2])).strip()

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
        "confianza_score": 98.0,
    }

