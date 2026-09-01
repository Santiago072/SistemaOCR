"""Lee el listado de referencia contra el que se cotejan las cedulas.

Acepta .xls, .xlsx y .csv, y trata de adivinar solo cuales columnas son el
documento, el tipo de documento y el nombre. Funciona con los dos formatos que
usa el SENA:

  * "Reporte de inscripcion": Identificacion ("CC - 1110487315") | Nombre
    ("ANDREA PATRICIA SANCHEZ PINZON") | Estado.
  * "Formato de inscripcion de aspirantes SOFIA Plus": Tipo de Identificacion
    ("TI"/"CC") | Numero de Identificacion, sin ninguna columna de nombre.

y con hojas normales que traigan columnas separadas de nombres y apellidos.
El nombre es opcional: si la hoja solo trae documentos, se cruza por numero.
"""
import csv
import io
import os
import re

from campos import compacto, normalizar, solo_digitos

# Como se suele llamar cada columna. Se busca por coincidencia parcial.
_COL_DOC = ["identificacion", "documento", "cedula", "cc", "nro documento",
            "numero de documento", "num documento", "no documento", "id", "nuip",
            "dni", "identificación", "cédula", "número de identificación", "nro de documento",
            "numero documento", "num_doc", "numdoc", "documento_identidad", "doc_identidad"]
_COL_NOMBRE_COMPLETO = ["nombre completo", "nombre", "nombres y apellidos",
                        "apellidos y nombres", "participante", "aprendiz", "aspirante",
                        "estudiante", "alumno", "titular", "persona", "tercero", "nombre(s) y apellido(s)",
                        "nombres_completos", "nombre y apellidos", "nombre_completo", "nombre y apellido"]
_COL_NOMBRES = ["nombres", "nombre(s)", "primer nombre", "primer_nombre", "nombres_completos"]
_COL_APELLIDOS = ["apellidos", "apellido(s)", "primer apellido", "primer_apellido", "segundo apellido", "apellidos_completos"]
_COL_TIPO = ["tipo de identificacion", "tipo de documento", "tipo documento",
             "tipo de doc", "tipo doc", "tipo id", "tipo_doc", "tipo_documento", "tipo_id"]

# Palabras que descartan una columna como la del numero. Sin esto, "Tipo de
# Identificacion" gana por contener "identificacion" y lo que se lee de ahi es
# "TI" o "CC", nunca un numero.
_NO_ES_NUMERO = ("tipo", "clase", "tipo de", "clase de", "tipo_doc", "tipo_id")

# Pistas de que la columna si trae el numero y no otra cosa.
_ES_NUMERO = ("numero", "número", "num", "nro", "no.", "n°", "#", "cedula", "cédula", "cc", "ti", "nuip", "id", "dni")

# Tipos de documento que usa la Registraduria. Solo TI y CC nos importan para
# comparar, pero los demas sirven para saber que la columna es la del tipo.
TIPOS_VALIDOS = {"CC", "TI", "CE", "PEP", "PPT", "DNI", "NCS", "PS", "RC", "NIT"}
EXT_ACEPTADAS = {".xlsx", ".xls", ".csv"}


def es_archivo_aceptado(nombre):
    return os.path.splitext(nombre)[1].lower() in EXT_ACEPTADAS


def _hojas(ruta, contenido=None):
    """Devuelve [(nombre_hoja, filas)] con cada celda ya pasada a texto.

    Las hojas van por separado a proposito: los formatos del SENA traen hojas
    escondidas con las listas de los desplegables (tipos de poblacion,
    resguardos...) y si se pegaran todas, esas listas se colarian como si
    fueran inscritos.
    """
    ext = os.path.splitext(ruta)[1].lower()

    if ext == ".csv":
        crudo = contenido if contenido is not None else open(ruta, "rb").read()
        texto = ""
        for cp in ("utf-8-sig", "latin-1"):
            try:
                texto = crudo.decode(cp)
                break
            except UnicodeDecodeError:
                continue
        # detecta si separa por coma o punto y coma
        muestra = texto[:2000]
        sep = ";" if muestra.count(";") > muestra.count(",") else ","
        filas = [[(c or "").strip() for c in fila]
                 for fila in csv.reader(io.StringIO(texto), delimiter=sep)]
        return [("", filas)]

    if ext == ".xls":
        import xlrd
        wb = (xlrd.open_workbook(file_contents=contenido) if contenido is not None
              else xlrd.open_workbook(ruta))
        hojas = []
        for sh in wb.sheets():
            filas = []
            for r in range(sh.nrows):
                fila = []
                for c in range(sh.ncols):
                    v = sh.cell_value(r, c)
                    if isinstance(v, float) and v == int(v):
                        v = int(v)
                    fila.append(str(v).strip())
                filas.append(fila)
            hojas.append((sh.name, filas))
        return hojas

    if ext in (".xlsx", ".xlsm"):
        import openpyxl
        wb = openpyxl.load_workbook(
            io.BytesIO(contenido) if contenido is not None else ruta,
            read_only=True, data_only=True)
        hojas = []
        for sh in wb.worksheets:
            filas = [["" if v is None else str(v).strip() for v in fila]
                     for fila in sh.iter_rows(values_only=True)]
            hojas.append((sh.title, filas))
        return hojas

    raise ValueError(f"No se puede leer un archivo {ext}. Usa .xls, .xlsx o .csv.")


def _titulo(celda):
    return normalizar(celda).lower().strip()


def _coincide(titulo, opciones):
    return any(titulo == o or (len(titulo) > 2 and o in titulo) for o in opciones)


def _busca_col(encabezado, opciones):
    """Indice de la primera columna cuyo titulo coincide con alguna opcion."""
    for i, celda in enumerate(encabezado):
        t = _titulo(celda)
        if t and _coincide(t, opciones):
            return i
    return None


def _col_documento(encabezado):
    """Indice de la columna con el NUMERO de documento.

    No basta con buscar "identificacion": el formato de SOFIA trae dos columnas
    que la contienen, "Tipo de Identificacion" y "Numero de Identificacion", y
    solo la segunda sirve. Se puntua para quedarse con la correcta.
    """
    mejor, mejor_puntos = None, 0
    for i, celda in enumerate(encabezado):
        t = _titulo(celda)
        if not t or any(x in t for x in _NO_ES_NUMERO):
            continue
        if not _coincide(t, _COL_DOC):
            continue
        puntos = 2 + any(w in t for w in _ES_NUMERO)
        if puntos > mejor_puntos:
            mejor, mejor_puntos = i, puntos
    return mejor


def _fila_encabezado(filas):
    """Busca la fila que hace de encabezado. Los reportes traen titulos antes.

    Se prefiere una fila que traiga documento Y nombre; si no hay ninguna, sirve
    una que traiga solo el documento (el formato de SOFIA no lleva nombres).
    """
    solo_doc = None
    for i, fila in enumerate(filas[:30]):
        if _col_documento(fila) is None:
            continue
        tiene_nombre = (_busca_col(fila, _COL_NOMBRE_COMPLETO) is not None
                        or _busca_col(fila, _COL_NOMBRES) is not None)
        if tiene_nombre:
            return i
        if solo_doc is None:
            solo_doc = i
    return solo_doc


def separar_nombre(completo, apellidos_pista=""):
    """Parte un nombre completo en (nombres, apellidos).

    El listado los da pegados y en orden NOMBRES + APELLIDOS, pero no
    dice donde corta. Si el OCR ya leyo los apellidos se usan como pista; si no,
    se aplica división inteligente reconociendo apellidos compuestos y partículas.
    """
    tokens = normalizar(completo).split()
    if not tokens:
        return "", ""
    if len(tokens) == 1:
        return tokens[0], ""

    if compacto(apellidos_pista):
        # Se prueba cada corte posible y se elige el que deje un bloque de
        # apellidos mas parecido a lo que leyo el OCR.
        from campos import parecido
        mejor, mejor_r = None, 0.0
        for k in range(1, len(tokens)):
            r = parecido(" ".join(tokens[k:]), apellidos_pista)
            if r > mejor_r:
                mejor, mejor_r = k, r
        if mejor is not None and mejor_r >= 0.70:
            return " ".join(tokens[:mejor]), " ".join(tokens[mejor:])

    # Manejo de partículas comunes en apellidos colombianos: "DE LA HOZ", "DE LOS RIOS", "DEL TORO", "SANCHEZ CONDE"
    # Si hay 4 tokens (ej: ARLINSON DANIEL MONTOYA MANCERA) -> 2 nombres, 2 apellidos
    if len(tokens) == 4:
        return " ".join(tokens[:2]), " ".join(tokens[2:])
    elif len(tokens) == 3:
        # Si el segundo token es DE, DEL, LA -> es apellido compuesto: 1 nombre, 2 apellidos
        if tokens[1] in ("DE", "DEL", "LA", "LOS", "SAN", "SANTA"):
            return tokens[0], " ".join(tokens[1:])
        # Por defecto 3 palabras suelen ser 1 nombre + 2 apellidos (ej: EDWAR FABIAN ESCALANTE o JORGE PEREZ GOMEZ)
        # o 2 nombres + 1 apellido. Se toma 1 nombre y 2 apellidos como estándar colombiano si no hay pista.
        return tokens[0], " ".join(tokens[1:])
    elif len(tokens) >= 5:
        # Detectar si hay partículas en los últimos tokens
        for i in range(1, len(tokens)):
            if tokens[i] in ("DE", "DEL", "DE LA", "DE LOS"):
                return " ".join(tokens[:i]), " ".join(tokens[i:])
        corte = max(1, len(tokens) - 2)
        return " ".join(tokens[:corte]), " ".join(tokens[corte:])

    corte = max(1, len(tokens) - 1)
    return " ".join(tokens[:corte]), " ".join(tokens[corte:])


def _leer_hoja(filas, i_enc):
    """Saca la gente de una hoja cuyo encabezado ya se ubico."""
    enc = filas[i_enc]
    c_doc = _col_documento(enc)
    c_tipo = _busca_col(enc, _COL_TIPO)
    c_ape = _busca_col(enc, _COL_APELLIDOS)
    c_nom = _busca_col(enc, _COL_NOMBRES)
    c_full = _busca_col(enc, _COL_NOMBRE_COMPLETO)
    # "Nombres" y "Nombre" chocan: si hay columna de apellidos aparte, manda esa pareja
    separadas = c_ape is not None and c_nom is not None
    if not separadas and c_full is None:
        c_full = c_nom
    usadas = {c_doc, c_tipo, c_full, c_nom, c_ape}

    def celda(fila, i):
        return fila[i] if i is not None and i < len(fila) else ""

    gente = []
    vistos = set()
    for fila in filas[i_enc + 1:]:
        doc = solo_digitos(celda(fila, c_doc))
        if not (6 <= len(doc) <= 11) or doc in vistos:
            continue

        if separadas:
            nombres = normalizar(celda(fila, c_nom))
            apellidos = normalizar(celda(fila, c_ape))
            completo = f"{nombres} {apellidos}".strip()
        else:
            completo = normalizar(celda(fila, c_full))
            tokens = completo.split()
            if len(tokens) >= 3:
                corte = max(1, len(tokens) - 2)
                nombres = " ".join(tokens[:corte])
                apellidos = " ".join(tokens[corte:])
            elif len(tokens) == 2:
                nombres, apellidos = tokens[0], tokens[1]
            elif len(tokens) == 1:
                nombres, apellidos = tokens[0], ""
            else:
                nombres = apellidos = ""

        celda_doc_raw = str(celda(fila, c_doc)).strip()
        tipo = normalizar(celda(fila, c_tipo)).replace(" ", "")
        if not tipo or tipo not in TIPOS_VALIDOS:
            m_tipo = re.search(r'^(CC|TI|CE|PEP|PPT|RC|DNI)\s*[-:]', celda_doc_raw.upper())
            tipo = m_tipo.group(1) if m_tipo else ''

        vistos.add(doc)
        extra = {enc[i]: fila[i] for i in range(min(len(enc), len(fila)))
                 if i not in usadas and enc[i] and fila[i]}
        gente.append({
            "documento": doc,
            "tipo": tipo,
            "nombre_completo": completo,
            "nombres": nombres,
            "apellidos": apellidos,
            "extra": extra,
        })
    return gente


def cargar(ruta, contenido=None):
    """Lee el listado y devuelve [{documento, tipo, nombre_completo, nombres,
    apellidos, extra}]. Se queda con la primera hoja que traiga inscritos."""
    hojas = _hojas(ruta, contenido)
    con_encabezado = False

    for _, filas in hojas:
        i_enc = _fila_encabezado(filas)
        if i_enc is None:
            continue
        con_encabezado = True
        gente = _leer_hoja(filas, i_enc)
        if gente:
            return gente

    if con_encabezado:
        raise ValueError(
            "El listado se leyó pero ninguna fila traía un número de documento "
            "válido (entre 6 y 11 dígitos). Revisa que la columna del número no "
            "esté vacía.")
    raise ValueError(
        "No encontré en el listado una columna con el número de documento. "
        "Revisa que la hoja tenga un encabezado como 'Número de Identificación', "
        "'Identificación', 'Documento' o 'Cédula'.")
