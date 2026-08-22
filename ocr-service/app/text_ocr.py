import re
import cv2
import numpy as np
from typing import Dict, Any, Optional, List

_ocr_engine = None

def get_ocr_engine():
    global _ocr_engine
    if _ocr_engine is None:
        try:
            from rapidocr_onnxruntime import RapidOCR
            _ocr_engine = RapidOCR(intra_op_num_threads=2, inter_op_num_threads=1)
        except Exception:
            try:
                from rapidocr_onnxruntime import RapidOCR
                _ocr_engine = RapidOCR()
            except Exception:
                _ocr_engine = False
    return _ocr_engine

COMMON_WORDS = [
    'JUAN', 'JOSE', 'LUIS', 'CARLOS', 'JORGE', 'MANUEL', 'JESUS', 'MIGUEL', 'DAVID', 'DANIEL', 'ANDRES',
    'DIEGO', 'SERGIO', 'ALEJANDRO', 'CAMILO', 'CRISTIAN', 'CRISTHIAM', 'SEBASTIAN', 'SANTIAGO', 'MATEO',
    'NICOLAS', 'FELIPE', 'ESTEBAN', 'BRAYAN', 'BRYAN', 'KEVIN', 'STIVEN', 'STEVEN', 'ESTIBEN', 'YOHAN',
    'JOHAN', 'JHON', 'JONATHAN', 'JOHN', 'FREDY', 'FREDDY', 'EDWIN', 'EDISON', 'EDINSON', 'WILMER',
    'ALEXANDER', 'ALEXANDRA', 'ANTONIO', 'JAVIER', 'RODRIGO', 'ALFONSO', 'LEONARDO', 'LEONEL', 'OSCAR',
    'SAMUEL', 'FERNANDO', 'RUBEN', 'PABLO', 'EMILIO', 'SAMIR', 'WALTER', 'VICTOR', 'CESAR', 'WILSON',
    'NELSON', 'DUVAN', 'YEISON', 'YHORLAN', 'ERLENDY', 'YODMAN', 'WILKIN', 'ARLINSON', 'KEINER',
    'DONALDO', 'ELIAN', 'DANILO', 'JHULIAN', 'EDWAR', 'FABIAN', 'MAURICIO',
    'MARIA', 'ANA', 'PAULA', 'ANDREA', 'DIANA', 'PAOLA', 'LAURA', 'NATALIA', 'NATALI', 'VALENTINA',
    'DANIELA', 'JULIANA', 'ERIKA', 'YULIANA', 'JASBLEIDY', 'YASMIN', 'LIDA', 'EUGENIA', 'YOLLYS',
    'MONICA', 'YENIFER', 'JENNIFER', 'ANGIE', 'KATHERINE', 'TATIANA', 'KAREN', 'VANESSA', 'PATRICIA',
    'RODRIGUEZ', 'GOMEZ', 'GONZALEZ', 'GARCIA', 'MARTINEZ', 'MARINEZ', 'LOPEZ', 'HERNANDEZ', 'PEREZ', 'SANCHEZ',
    'RAMIREZ', 'TORRES', 'DIAZ', 'VARGAS', 'CASTRO', 'ROJAS', 'ALVAREZ', 'RUIZ', 'SUAREZ', 'MORENO',
    'MUNOZ', 'MUÑOZ', 'JIMENEZ', 'GUTIERREZ', 'VALENCIA', 'QUINTERO', 'MEJIA', 'ORTEGA', 'DELGADO',
    'MEDINA', 'CONTRERAS', 'MORALES', 'SILVA', 'PINZON', 'BERNAL', 'PARRA', 'URBANO', 'CARDONA',
    'RESTREPO', 'HERRERA', 'RIVERA', 'OSORIO', 'GUZMAN', 'MENDOZA', 'CORDOBA', 'ARIAS', 'VELASQUEZ',
    'CACERES', 'SALAZAR', 'TOVAR', 'MARIN', 'AGUILAR', 'CRUZ', 'REYES', 'PACHECO', 'RAMOS', 'DUARTE',
    'CAMACHO', 'PENAGOS', 'GUARNIZO', 'POSADA', 'FIERRO', 'GUACA', 'SEPULVEDA', 'CALA', 'BASTIDAS',
    'ORTIZ', 'ALDANA', 'BOHORQUEZ', 'BOHOROUEZ', 'VERA', 'REINA', 'CHAVARRO', 'GARAVITO', 'PINEDA',
    'URREA', 'MURCIA', 'ARTUNDUAGA', 'SAENZ', 'OYOLA', 'SABI', 'JOVEN', 'URAZAN', 'VILLEGAS',
    'ANDRADE', 'CADENA', 'ZULUAICA', 'CHARO', 'MOSQUERA', 'ESCOBAR', 'MURIEL', 'CALDERON', 'CASTILLO',
    'CLAROS', 'COLLAZOS', 'CONDE', 'DEVIA', 'GUEVARA', 'HOYOS', 'MANCERA', 'MONTOYA', 'POLOCHE', 'QUIROZ', 'TORO',
    'AYALA'
]

def split_token_recursive(tok: str) -> list:
    tok_u = tok.upper()
    if len(tok_u) < 6:
        return [tok]
    
    for cw in sorted(COMMON_WORDS, key=len, reverse=True):
        if tok_u.startswith(cw) and len(tok_u) > len(cw):
            rest = tok_u[len(cw):]
            if len(rest) >= 3:
                return [cw] + split_token_recursive(rest)
    return [tok]

def split_joined_words(text: str) -> str:
    if not text: return ""
    tokens = text.split()
    out_tokens = []
    for t in tokens:
        out_tokens.extend(split_token_recursive(t))
    return " ".join(out_tokens)

STOP_OCR_WORDS = {
    'REPUBLICA', 'REPUBLICADECOLOMBIA', 'COLOMBIA', 'DECOLOMBIA', 'DE', 'LA', 'DEL', 'OLOMBA', 'OLOMBIA', 'CEDULA', 'CIUDADANIA', 
    'IDENTIFICACION', 'PERSONAL', 'NUMERO', 'NUMERD', 'NOMERO', 'NÚMERO', 'NOSEHO', 'IUMRO', 'APELLIDOS', 'APELLDOS', 'APELUDOS', 'APELIDOS', 'APELLIDO',
    'NOMBRES', 'NOMBRE', 'NOMORES', 'NDMOAES', 'NBMOAES', 'FIRMA', 'FIRMK', 'FINMA', 'FECHA', 'NACIMIENTO', 'EXPEDICION', 'LUGAR', 
    'ESTATURA', 'SEXO', 'G.S.', 'RH', 'REGISTRADOR', 'NACIONAL', 'INDICE', 'DERECHO', 'CAMSCANNER', 
    'POWERED', 'SCANNED', 'WITH', 'PURLICA', 'PERCAE', 'FECHADENACIMIENTO', 'FECHAOC', 'NACIONALIDAD', 'EXPIRACION',
    'CONTRASENA', 'CONTRASEÑA', 'PRIMERAVEZCC', 'COMPROBANTE', 'VALIDO', 'REGISTRADURIA', 'ESTECOMPROBANTEESVALIDO',
    'OILES', 'OLES', 'ARELLOSNOMIRE', 'ARELIEOSNOMIE'
}

def clean_text_word(w: str) -> str:
    w_norm = w.upper().replace('!', 'I').replace('|', 'I').replace('1', 'I')
    w_clean = re.sub(r'[^A-ZÁÉÍÓÚÜÑ]', '', w_norm)
    return w_clean if len(w_clean) >= 2 and w_clean not in STOP_OCR_WORDS else ''

def clean_line_name(line_str: str) -> str:
    words = [clean_text_word(w) for w in line_str.split()]
    cleaned_str = " ".join([w for w in words if w]).strip()
    return split_joined_words(cleaned_str)

def parse_id_card_lines(lines: List[str]) -> Optional[Dict[str, Any]]:
    full_text = " ".join(lines).upper()

    doc_number = None
    apellidos = ""
    nombres = ""
    doc_index = -1

    for i, line in enumerate(lines):
        l_u = line.strip().upper()
        m_nuip = re.search(r'(?:NUIP|NUMERO|NÚMERO|NO\.?|PRIMERAVEZCC)\s*[:\.\s]*([\d]{1,4}(?:\.[\d]{3}){1,3}|\d{7,10})', l_u)
        if m_nuip:
            doc_number = m_nuip.group(1).replace('.', '').replace(' ', '').strip()
            doc_index = i
            break
        
        m_dot = re.search(r'(?:^|[^\d])(\d{1,4}(?:\.\d{3}){2,3})(?:[^\d]|$)', l_u)
        if m_dot:
            doc_number = m_dot.group(1).replace('.', '').strip()
            doc_index = i
            break

    if not doc_number:
        for line in lines:
            l_clean = line.replace(' ', '').upper()
            m_mrz_doc = re.search(r'C[0O]L(\d{6,10})', l_clean)
            if m_mrz_doc:
                doc_number = m_mrz_doc.group(1).lstrip('0')

    if not doc_number:
        m_bar_foot = re.search(r'-[MF]-0*(\d{6,10})-', full_text)
        if m_bar_foot:
            doc_number = m_bar_foot.group(1)

    if not doc_number:
        for i, line in enumerate(lines):
            m_any = re.search(r'\b(1\d{8,9}|\d{7,8})\b', line)
            if m_any and not line.startswith('851'):
                doc_number = m_any.group(1)
                doc_index = i
                break

    for i, line in enumerate(lines):
        l_u = line.upper()

        if any(k in l_u for k in ['ARELIE', 'ARELL', 'APELLID']) and any(k in l_u for k in ['NOMIE', 'NOMIRE', 'NOMBR', 'NOMI']) and (not apellidos or not nombres):
            if i + 1 < len(lines):
                c1 = clean_line_name(lines[i + 1])
                if c1 and c1 not in STOP_OCR_WORDS and not re.search(r'\d', lines[i + 1]):
                    apellidos = c1
            if i + 2 < len(lines):
                c2 = clean_line_name(lines[i + 2])
                if c2 and c2 not in STOP_OCR_WORDS and not re.search(r'\d', lines[i + 2]):
                    nombres = c2

        if ('APELLID' in l_u or 'APELUD' in l_u) and not apellidos:
            parts = re.split(r'APELLID[A-Z]*\s*[:\.]?\s*', l_u)
            c_same = clean_line_name(parts[1]) if len(parts) > 1 and parts[1].strip() else ''
            
            c_prev = ''
            for k in range(i - 1, max(-1, i - 4), -1):
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lines[k].upper() and not any(h in lines[k].upper() for h in ['REPUBLIC', 'CIUDAD', 'IDENTIFIC', 'CEDULA']):
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3:
                        c_prev = cand
                        break

            c_next = ''
            for k in range(i + 1, min(len(lines), i + 4)):
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lines[k].upper():
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3:
                        c_next = cand
                        break

            if c_same:
                apellidos = c_same
            elif c_prev:
                apellidos = c_prev
            elif c_next:
                apellidos = c_next

        if any(k in l_u for k in ['NOMBR', 'NOMOR', 'NDMOA', 'NBMOA', 'NOMRES']) and not nombres:
            parts = re.split(r'(?:NOMBR|NOMOR|NDMOA|NBMOA|NOMRES)[A-Z]*\s*[:\.]?\s*', l_u)
            c_same = clean_line_name(parts[1]) if len(parts) > 1 and parts[1].strip() else ''
            
            c_prev = ''
            for k in range(i - 1, max(-1, i - 4), -1):
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lines[k].upper() and not any(h in lines[k].upper() for h in ['REPUBLIC', 'CIUDAD', 'IDENTIFIC', 'CEDULA']):
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3 and cand != apellidos:
                        c_prev = cand
                        break

            c_next = ''
            for k in range(i + 1, min(len(lines), i + 4)):
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lines[k].upper():
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3 and cand != apellidos:
                        c_next = cand
                        break

            if c_same:
                nombres = c_same
            elif c_prev:
                nombres = c_prev
            elif c_next:
                nombres = c_next

    if doc_index >= 0 and (not apellidos or not nombres):
        candidatos = []
        for j in range(doc_index + 1, min(len(lines), doc_index + 8)):
            cleaned = clean_line_name(lines[j])
            if re.search(r'\d{2}-[A-Z]{3}-\d{4}', lines[j]) or 'FIRMA' in lines[j].upper() or 'ICC0L' in lines[j].upper() or '<<<' in lines[j]:
                break
            if cleaned and len(cleaned) >= 3 and cleaned not in STOP_OCR_WORDS and not re.search(r'\d', lines[j]):
                candidatos.append(cleaned)

        if len(candidatos) >= 2:
            if not apellidos: apellidos = candidatos[0]
            if not nombres: nombres = candidatos[1]
        elif len(candidatos) == 1:
            if not apellidos and not nombres:
                parts = candidatos[0].split()
                if len(parts) >= 2:
                    apellidos = parts[0]
                    nombres = " ".join(parts[1:])
                else:
                    nombres = candidatos[0]
            elif not apellidos:
                apellidos = candidatos[0]
            elif not nombres:
                nombres = candidatos[0]

    if not apellidos or not nombres:
        for line in lines:
            l_clean = line.replace(' ', '').upper()
            m_mrz_names = re.search(r'([A-Z]+(?:<[A-Z]+)*)<<([A-Z]+(?:<[A-Z]+)*)', l_clean)
            if m_mrz_names:
                if not apellidos:
                    apellidos = split_joined_words(m_mrz_names.group(1).replace('<', ' ').strip())
                if not nombres:
                    nombres = split_joined_words(m_mrz_names.group(2).replace('<', ' ').strip())
                break

    if doc_number:
        apellidos = split_joined_words(apellidos)
        nombres = split_joined_words(nombres)

        REEMPLAZOS_ENYE = {
            r'\bMUNOZ\b': 'MUÑOZ',
            r'\bMONTANA\b': 'MONTAÑA',
            r'\bNINO\b': 'NIÑO',
            r'\bPENA\b': 'PEÑA',
            r'\bIBANEZ\b': 'IBAÑEZ',
            r'\bCASTANO\b': 'CASTAÑO',
            r'\bBOHOROUEZ\b': 'BOHORQUEZ',
            r'\bMARIEZ\b': 'MARTINEZ'
        }
        for pat, rep in REEMPLAZOS_ENYE.items():
            apellidos = re.sub(pat, rep, apellidos)
            nombres = re.sub(pat, rep, nombres)

        nombre_completo = f"{nombres} {apellidos}".strip() if (nombres or apellidos) else ""

        ap_parts = apellidos.split()
        nom_parts = nombres.split()

        return {
            "exito": True,
            "tipo_documento": "TI" if "TARJETA" in full_text else "CC",
            "numero_documento": doc_number,
            "primer_apellido": ap_parts[0] if len(ap_parts) > 0 else "",
            "segundo_apellido": " ".join(ap_parts[1:]) if len(ap_parts) > 1 else "",
            "primer_nombre": nom_parts[0] if len(nom_parts) > 0 else "",
            "segundo_nombre": " ".join(nom_parts[1:]) if len(nom_parts) > 1 else "",
            "nombre_completo_ocr": nombre_completo,
            "genero": None,
            "fecha_nacimiento": None,
            "rh": None,
            "metodo_extraccion": "OCR_PADDLE",
            "confianza_score": 90.00
        }

    return None

def extract_text_from_id(image: np.ndarray) -> Optional[Dict[str, Any]]:
    engine = get_ocr_engine()
    if not engine:
        return None

    try:
        h, w = image.shape[:2]
        max_dim = 1280
        if max(h, w) > max_dim:
            scale = max_dim / max(h, w)
            img_infer = cv2.resize(image, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)
        else:
            img_infer = image

        result, _ = engine(img_infer)
        if not result:
            return None

        detected_lines = [item[1].strip() for item in result if item[1].strip()]
        return parse_id_card_lines(detected_lines)
    except Exception:
        return None
