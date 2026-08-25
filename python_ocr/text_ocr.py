"""
Motor de Extracción OCR Visual con Red Neuronal (RapidOCR ONNX)
Reconoce texto impreso, cédulas digitales (NUIP / MRZ) y cédulas físicas
con soporte nativo para caracteres en español y orientación multi-ángulo.
"""

import re
import cv2
import numpy as np
from typing import Dict, Any, Optional, List

# Inicialización diferida (Lazy) del motor ONNX para no sobrecargar memoria
_ocr_engine = None

def get_ocr_engine():
    global _ocr_engine
    if _ocr_engine is None:
        try:
            from rapidocr_onnxruntime import RapidOCR
            # Optimización ONNX: 2 hilos por proceso para máxima eficiencia CPU sin contención
            _ocr_engine = RapidOCR(intra_op_num_threads=2, inter_op_num_threads=1)
        except Exception:
            try:
                from rapidocr_onnxruntime import RapidOCR
                _ocr_engine = RapidOCR()
            except Exception:
                _ocr_engine = False
    return _ocr_engine

def extract_text_from_id(image: np.ndarray) -> Optional[Dict[str, Any]]:
    """
    Ejecuta OCR sobre la imagen y parsea los campos de la cédula de forma ultra-rápida.
    """
    engine = get_ocr_engine()
    if not engine:
        return None

    try:
        # Redimensionar a una escala óptima para inferencia precisa en CPU (max 1280px)
        h, w = image.shape[:2]
        max_dim = 1280
        if max(h, w) > max_dim:
            scale = max_dim / max(h, w)
            img_infer = cv2.resize(image, (int(w * scale), int(h * scale)), interpolation=cv2.INTER_AREA)
        else:
            img_infer = image

        # RapidOCR analiza orientación automáticamente y segmenta líneas
        result, _ = engine(img_infer)

        if not result:
            return None

        detected_lines = [item[1].strip() for item in result if item[1].strip()]
        return parse_id_card_lines(detected_lines)
    except Exception as e:
        return None



# Diccionario exhaustivo de nombres y apellidos frecuentes en Colombia para desacoplar palabras pegadas
COMMON_WORDS = [
    # Nombres masculinos y femeninos frecuentes en Colombia
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
    
    # Apellidos frecuentes en Colombia
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
    'AYALA', 'VANEGAS', 'BARRERA', 'CARVAJAL', 'TRUJILLO', 'OSPINA', 'ZAPATA'
]

def split_token_recursive(tok: str) -> list:
    """Divide recursivamente una palabra pegada buscando prefijos y sufijos en el léxico colombiano"""
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
    """Desacopla palabras pegadas (ej: 'BRAYANSTIVEN' -> 'BRAYAN STIVEN', 'CHAROMOSQUERA' -> 'CHARO MOSQUERA')"""
    if not text: return ""
    tokens = text.split()
    out_tokens = []
    for t in tokens:
        out_tokens.extend(split_token_recursive(t))
    return " ".join(out_tokens)

STOP_OCR_WORDS = {
    'REPUBLICA', 'REPUBLICADECOLOMBIA', 'COLOMBIA', 'DECOLOMBIA', 'DE', 'LA', 'DEL', 'OLOMBA', 'OLOMBIA', 'CEDULA', 'CIUDADANIA', 
    'IDENTIFICACION', 'PERSONAL', 'NUMERO', 'NUMERD', 'NOMERO', 'NÚMERO', 'NOSEHO', 'IUMRO', 'APELLIDOS', 'APELLDOS', 'APELUDOS', 'APELIDOS', 'APELLIDO',
    'NOMBRES', 'NOMBRE', 'NOMORES', 'NDMOAES', 'NBMOAES', 'NOMBAES', 'NDMGRES', 'OMORES', 'NOMTRES', 'NOMTRE', 'FIRMA', 'FIRMK', 'FINMA', 'IRMA', 'HMA', 'FECHA', 'NACIMIENTO', 'EXPEDICION', 'LUGAR', 
    'ESTATURA', 'SEXO', 'SEXD', 'G.S.', 'RH', 'REGISTRADOR', 'REGISTRADORA', 'REGISTRADORNACIONAL', 'REGISTRADORANACIONAL', 'NACIONAL', 'INDICE', 'DERECHO', 'CAMSCANNER', 'CSCAMSCANNER', 'CS',
    'POWERED', 'SCANNED', 'WITH', 'PURLICA', 'PERCAE', 'FECHADENACIMIENTO', 'FECHAOC', 'NACIONALIDAD', 'EXPIRACION',
    'CONTRASENA', 'CONTRASEÑA', 'PRIMERAVEZCC', 'COMPROBANTE', 'VALIDO', 'REGISTRADURIA', 'ESTECOMPROBANTEESVALIDO',
    'OILES', 'OLES', 'ARELLOSNOMIRE', 'ARELIEOSNOMIE', 'ARELLDOSNOUIE', 'ARELLDOS', 'NOUIE', 'NOMIE', 'NOMIRE', 'EOLOMAPA', 'LDE',
    'COL', 'ALEXANDER', 'ALEXANDERVEGA', 'AIEXANDERVEGA', 'ROCHA', 'HERNAN', 'PENAGOS', 'GIRALDO', 'CARLOSARIELSANCHEZTORRES', 'ALMABEATRIZ',
    'ICADEC', 'CADECD', 'COLBUSAA', 'BUSAA', 'BUSLA', 'DEC'
}

def clean_text_word(w: str) -> str:
    w_norm = w.upper().replace('!', 'I').replace('|', 'I').replace('1', 'I').replace('/', '')
    w_clean = re.sub(r'[^A-ZÁÉÍÓÚÜÑ]', '', w_norm)
    return w_clean if len(w_clean) >= 2 and w_clean not in STOP_OCR_WORDS else ''

def clean_line_name(line_str: str) -> str:
    words = [clean_text_word(w) for w in line_str.split()]
    cleaned_str = " ".join([w for w in words if w]).strip()
    return split_joined_words(cleaned_str)

def extract_document_number(text: str) -> Optional[str]:
    # 1. Formato con prefijo NUMERO / NUIP / PRIMERAVEZCC (ej: NUIP 1.117.811.433 o NUMERO 1.077.868.396)
    m_nuip = re.search(r'(?:NUIP|NUMERO|NÚMERO|NO\.?|PRIMERAVEZCC)\s*[:\.\s]*([\d]{1,4}(?:\.[\d]{3}){1,3}|\d{7,10})', text.upper())
    if m_nuip:
        num = m_nuip.group(1).replace('.', '').replace(' ', '').strip()
        if len(num) >= 7 and not num.startswith('851'):
            return num

    # 2. Formato con puntos exacto sin prefijo (ej: 1.117.811.433 o 1.077.868.396)
    m_dot = re.search(r'(?:^|[^\d])(\d{1,4}(?:\.\d{3}){2,3})(?:[^\d]|$)', text)
    if m_dot:
        return m_dot.group(1).replace('.', '').strip()

    # 3. Patrón MRZ de cédula (Línea 2 MRZ: 6 dígitos fecha + F/M + dígitos + C0L + NÚMERO CÉDULA)
    # Ej: 8705080F3302244C0L1117493336<3 o 8606073M3308165C0L1127070363<5
    m_mrz_line2 = re.search(r'\d+[MF]\d*C[0O]L(\d{6,10})', text.replace(' ', '').upper())
    if m_mrz_line2:
        return m_mrz_line2.group(1).lstrip('0')
    
    # Si viene C0L directo sin prefijo IC (para evitar el número de serie ICC0L... de la línea 1)
    if 'ICC' not in text.upper():
        m_mrz = re.search(r'(?<!IC)C[0O]L(\d{6,10})', text.replace(' ', '').upper())
        if m_mrz:
            return m_mrz.group(1).lstrip('0')

    # 4. Número al pie del código de barras (ej: P-4400100-0100235-F-1117563913-20180507 o P-1903400-00397062-M-1077868396-20120905)
    m_bar_foot = re.search(r'-[MF]-0*(\d{6,10})-', text.replace(' ', '').upper())
    if m_bar_foot:
        return m_bar_foot.group(1).lstrip('0')

    # 5. Fallback dígitos sueltos de cédula colombiana (7 a 10 dígitos)
    m_any = re.search(r'\b(1\d{8,9}|\d{7,8})\b', text)
    if m_any:
        cand = m_any.group(1)
        if not cand.startswith('851') and not cand.startswith('4400') and not cand.startswith('1903') and not cand.startswith('0134') and not cand.startswith('0726') and not cand.startswith('0299'):
            return cand
    return None

def parse_id_card_lines(lines: List[str]) -> Optional[Dict[str, Any]]:
    """
    Parsea las líneas detectadas por el OCR para Cédula Tradicional, Cédula Digital y Contraseña de la Registraduría.
    Maneja etiquetas dañadas o ausentes mediante estructura posicional estándar de la Registraduría.
    """
    full_text = " ".join(lines).upper()

    doc_number = None
    doc_index = -1

    # 1. Extraer número de documento analizando línea por línea
    for idx, line in enumerate(lines):
        d = extract_document_number(line)
        if d:
            doc_number = d
            doc_index = idx
            break

    # 2. Buscar en todo el texto si no se encontró en líneas individuales
    if not doc_number:
        # Prioridad A: MRZ en reversos digitales (ej: 8705080F3302244C0L1117493336<3)
        m_mrz_doc = re.search(r'C[0O]L(\d{6,10})', full_text.replace(' ', ''))
        if m_mrz_doc:
            doc_number = m_mrz_doc.group(1).lstrip('0')
        else:
            # Prioridad B: Pie del código de barras (ej: P-4400100-0100235-F-1117563913-20180507)
            m_bar_foot = re.search(r'-[MF]-0*(\d{6,10})-', full_text.replace(' ', ''))
            if m_bar_foot:
                doc_number = m_bar_foot.group(1).lstrip('0')
            else:
                doc_number = extract_document_number(full_text)

    apellidos = ""
    nombres = ""

    # A) Buscar si hay etiquetas explícitas de Apellidos y Nombres (Cédula Tradicional y Digital frontal)
    for i, line in enumerate(lines):
        l_u = line.upper()

        # 1. Detectar etiqueta combinada en Contraseñas Registraduría (ej: 'ARELIEOSNOMIE', 'APELLDOS/NOUIE', 'ARELLDOS/NOUIE', 'APELLIDOS Y NOMBRES', 'APELLIDOS NOMBRES')
        if any(k in l_u for k in ['ARELIE', 'ARELL', 'APELLID', 'APELLD', 'APELUD', 'APELDD', 'APELID']) and any(k in l_u for k in ['NOMIE', 'NOMIRE', 'NOMBR', 'NOMI', 'NOUIE', 'NOMRES', 'NOMBAE', 'NDMGRE']) and (not apellidos or not nombres):
            if i + 1 < len(lines):
                c1 = clean_line_name(lines[i + 1])
                if c1 and c1 not in STOP_OCR_WORDS and not re.search(r'\d', lines[i + 1]):
                    apellidos = c1
            if i + 2 < len(lines):
                c2 = clean_line_name(lines[i + 2])
                if c2 and c2 not in STOP_OCR_WORDS and not re.search(r'\d', lines[i + 2]):
                    nombres = c2

        is_digital = 'NUIP' in full_text

        # 2. Detectar etiqueta APELLIDOS (ej: APELLIDOS, APELUDOS, APELDDOS, APELIDOS)
        if any(k in l_u for k in ['APELLID', 'APELUD', 'APELDD', 'APELID']) and not apellidos:
            parts = re.split(r'(?:APELLID|APELUD|APELDD|APELID)[A-Z]*\s*[:\.]?\s*', l_u)
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
            elif is_digital:
                apellidos = c_next if c_next else c_prev
            else:
                apellidos = c_prev if c_prev else c_next

        # 3. Detectar variantes de etiqueta NOMBRES (ej. NOMBRES, NOMORES, NOMTRES, NDMOAES, NBMOAES, NOMBR, NOMIBRES, NOMIBRE, NOMBAES, NDMGRES, OMORES)
        if any(k in l_u for k in ['NOMBR', 'NOMOR', 'NOMTRE', 'NDMOA', 'NBMOA', 'NOMRES', 'NOMIBR', 'NOMIR', 'NOMBAE', 'NDMGRE', 'OMORE']) and not nombres:
            parts = re.split(r'(?:NOMBR|NOMOR|NOMTRE|NDMOA|NBMOA|NOMRES|NOMIBR|NOMIR|NOMBAE|NDMGRE|OMORE)[A-Z]*\s*[:\.]?\s*', l_u)
            c_same = clean_line_name(parts[1]) if len(parts) > 1 and parts[1].strip() else ''
            
            c_prev = ''
            for k in range(i - 1, max(-1, i - 4), -1):
                lk_u = lines[k].upper()
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lk_u and not any(h in lk_u for h in ['REPUBLIC', 'CIUDAD', 'IDENTIFIC', 'CEDULA', 'HEPUS', 'PURLIC', 'COLOMB']):
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3 and cand != apellidos:
                        c_prev = cand
                        break

            c_next = ''
            for k in range(i + 1, min(len(lines), i + 4)):
                lk_u = lines[k].upper()
                if not re.search(r'\d', lines[k]) and 'NUIP' not in lk_u and not any(h in lk_u for h in ['REPUBLIC', 'CIUDAD', 'IDENTIFIC', 'CEDULA', 'HEPUS', 'PURLIC', 'COLOMB']):
                    cand = clean_line_name(lines[k])
                    if cand and cand not in STOP_OCR_WORDS and len(cand) >= 3 and cand != apellidos:
                        c_next = cand
                        break

            if c_same:
                nombres = c_same
            elif is_digital:
                nombres = c_next if c_next else c_prev
            else:
                nombres = c_prev if c_prev else c_next

    # B) Búsqueda posicional si aún falta alguno (en cédulas digitales o tradicionales sin etiquetas explícitas)
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

    # C) Fallback: Buscar patrón MRZ si aún falta apellidos o nombres
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
        # Desacoplar palabras pegadas (ej: VANEGASMUNOZ -> VANEGAS MUNOZ)
        apellidos = split_joined_words(apellidos)
        nombres = split_joined_words(nombres)

        # Restaurar letra Ñ en apellidos colombianos comunes donde el OCR o el PDF la leyó como N
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

        # Evitar duplicaciones (ej: si apellidos es "ALDANA BOHORQUEZ" y nombres también se leyó como "ALDANA BOHORQUEZ")
        ap_words = apellidos.split()
        nom_words = nombres.split()

        # Si nombres contiene las mismas palabras que apellidos, limpiar nombres
        if [w for w in nom_words if w in ap_words] == nom_words and nom_words:
            nombres = ""
            nom_words = []

        # Si no hay nombres, pero apellidos tiene 3 o más palabras (ej: LIDA YASMIN ALDANA BOHORQUEZ)
        if not nombres and len(ap_words) >= 3:
            # En cédulas tradicionales a veces se lee todo junto en una sola línea
            # Los nombres van primero o último según el contexto
            pass

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
            "metodo_extraccion": "OCR_RAPID",
            "confianza_score": 90.00
        }

    return None


