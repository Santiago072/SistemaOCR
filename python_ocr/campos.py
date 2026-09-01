"""Interpreta las lineas sueltas que devuelve el OCR y arma los campos de la cedula.

Maneja los dos disenos de cedula colombiana que aparecen en los escaneos:

  * "amarilla" (la vieja): el valor va ARRIBA y la etiqueta debajo.
        1.077.868.396
        NUMERO
        CHAVARRO GARAVITO
        APELLIDOS

  * "nueva" (la de 2020 en adelante): la etiqueta va ARRIBA y el valor debajo.
        Apellidos
        FIERRO GUACA

Una pagina puede traer solo el frente, solo el reverso, o los dos.
"""
import re
import unicodedata
from difflib import SequenceMatcher

# ---------------------------------------------------------------- utilidades

def sin_tildes(s):
    """Quita tildes pero conserva la enie, que si distingue apellidos (MUNOZ / MUÑOZ)."""
    s = (s or "").replace("Ñ", "\0").replace("ñ", "\0")
    s = unicodedata.normalize("NFD", s)
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    return s.replace("\0", "Ñ")


def normalizar(s):
    """MAYUSCULAS, sin tildes, con espacios colapsados."""
    return re.sub(r"\s+", " ", sin_tildes(s).upper()).strip()


def compacto(s):
    """Solo letras y digitos. Sirve para comparar aunque el OCR se coma espacios.
    La enie se equipara a N porque el OCR la pierde a menudo."""
    return re.sub(r"[^A-Z0-9]", "", normalizar(s).replace("Ñ", "N"))


def parecido(a, b):
    return SequenceMatcher(None, compacto(a), compacto(b)).ratio()


# Que tanto se le cree a cada origen del dato.
#   mrz    -> zona legible por maquina del reverso de la cedula nueva
#   serial -> pie del codigo de barras del reverso de la amarilla
#   frente -> lo impreso en el anverso, junto a su etiqueta
# El MRZ y el frente empatan: los dos se leen bien. El serial va un escalon
# abajo porque es letra chiquita y el OCR le cambia digitos.
#   grande -> texto sin etiqueta pero impreso en grande, como el numero del frente
#   suelto -> un numero que aparecio por ahi, en letra chica y sin etiqueta
CONFIABILIDAD = {"frente": 3, "mrz": 3, "reverso": 2, "serial": 2, "grande": 2, "suelto": 1}


def preferir(a, b, clave):
    """Elige entre dos lecturas del mismo campo. Devuelve (gana, pierde)."""
    if a is None:
        return b, None
    if b is None:
        return a, None
    if compacto(a["valor"]) == compacto(b["valor"]):
        # dicen lo mismo: se queda el que separe mejor las palabras, porque el
        # frente a veces las pega ("ANDRADECADENA") y el MRZ no
        return (a, b) if len(a["valor"].split()) >= len(b["valor"].split()) else (b, a)

    if clave == "documento":
        # En documentos, preferir números válidos colombianos completos (8 o 10 dígitos) sobre fragmentos truncados
        def _score_doc(d):
            val = solo_digitos(d.get("valor", ""))
            f_score = CONFIABILIDAD.get(d.get("fuente"), 1) * 10
            len_score = 20 if len(val) in (8, 10) else (10 if len(val) >= 7 else 0)
            return (len_score + f_score, d.get("conf", 0))
        orden = _score_doc
    elif clave in ("nacimiento", "expedicion"):
        orden = lambda d: (CONFIABILIDAD.get(d.get("fuente"), 1), d.get("conf", 0))
    else:
        # En nombres/apellidos: la banda MRZ tiene un límite de 30 caracteres por línea y trunca nombres largos al final
        # (ej. frente: YOHAN SEBASTIAN, MRZ: YOHAN SEBASTIA | frente: VANESSA ALEXANDRA, MRZ: VANESSA ALE).
        # Si el frente y el MRZ coinciden en su raíz, preferir la versión que tenga la palabra completa o mayor longitud.
        ca, cb = compacto(a["valor"]), compacto(b["valor"])
        if ca.startswith(cb) and len(ca) > len(cb):
            return (a, b)
        if cb.startswith(ca) and len(cb) > len(ca):
            return (b, a)
        
        # Si ambos son similares (>= 80%) y uno es más largo (más de 2 letras), el más largo es el no truncado
        sim = parecido(ca, cb)
        if sim >= 0.80:
            if len(ca) >= len(cb) + 2:
                return (a, b)
            elif len(cb) >= len(ca) + 2:
                return (b, a)

        orden = lambda d: (CONFIABILIDAD.get(d.get("fuente"), 1), len(d.get("valor", "").split()), len(compacto(d.get("valor", ""))), d.get("conf", 0))
    return (a, b) if orden(a) >= orden(b) else (b, a)


# El OCR confunde estos caracteres dentro de palabras. Solo se aplica a campos
# de texto (nombres, ciudad), nunca al numero de documento.
_ARREGLOS = {"!": "I", "|": "I", "0": "O", "1": "I", "5": "S", "8": "B", "$": "S"}


def limpiar_texto(s):
    s = normalizar(s)
    s = "".join(_ARREGLOS.get(c, c) for c in s)
    # Eliminar texto de encabezados o etiquetas de expedición / fecha deterioradas que el OCR haya pegado al nombre
    s = re.sub(r'\b(FECHA|LUGAR|EXPEDICION|EXPEDIC|PEDICION|PEDCON|FECHAYLUGAR|FECHAYLGARDEEKPECON|FEOHAYLGAROEE|FECHAYLUGARDEE\w*)\b', '', s)
    s = re.sub(r'(\w{3,})(DELA)(\b|\s|\w{2,})', r'\1 DE LA \3', s)
    s = re.sub(r'(\w{3,})(DELOS)(\b|\s|\w{2,})', r'\1 DE LOS \3', s)
    s = re.sub(r'\bDELA\b', 'DE LA', s)
    s = re.sub(r'(\w{3,})(MUNOZ|MUÑOZ)\b', r'\1 \2', s)
    s = re.sub(r'(\w{3,})(GOMEZ|PEREZ|LOPEZ|RODRIGUEZ|MARTINEZ|GARCIA|SANCHEZ|RAMIREZ|GONZALEZ|TORRES|DIAZ|VARGAS|CASTRO|MORALES|ROJAS|ORTIZ|SILVA|MORENO|CONDE|ARIZA|DEVIA|HOYOS|ANDRADE|CLAROS|PINEDA|URAZAN|POSADA|REYES|GUARNIZO|TOVAR|VILLEGAS|VAQUIRO|LEYTON|CABEZAS|CALDERON)\b', r'\1 \2', s)
    s = re.sub(r'^(KELLY|YOVANNY|YOHAN|JUAN|JHON|CRISTHIAM|CRISTIAN|BRAYAN|EDWAR|EDWARD|VICTOR|WILMER|WILKIN|CARLOS|DANIEL|DIEGO|JORGE|JOSE|LUIS|LUIZ|MIGUEL|OSCAR|PABLO|PEDRO|RUBEN|SERGIO|YEISON|YENIFER|YHORLAN|YODMAN|ANDREA|DIANA|ERIKA|JASBLEIDY|LAURA|LIDA|MARIA|MONICA|NATALI|PAULA|VANESSA)(TATIANA|ALBEIRO|ELIECER|SEBASTIAN|STIVEN|STEVEN|MAURICIO|FABIAN|MANUEL|ALEXANDER|ANDRES|EDWIN|ENRIQUE|ARMANDO|FREDDY|JAMEZ|ELIAN|ERLENDY|ANGEL|LEONARDO|EMILIO|FERNANDO|PATRICIA|JAVIER|SANTIAGO|PAOLA|YULIANA|YOLLYS|YASMIN|EUGENIA|ALEJANDRA)\b', r'\1 \2', s)
    s = re.sub(r'\bMUNOZ\b', 'MUÑOZ', s)
    s = re.sub(r'\bMONTANA\b', 'MONTAÑA', s)
    s = re.sub(r'\bREPU\w*\b', '', s)
    return re.sub(r"\s+", " ", re.sub(r"[^A-ZÑ ]", " ", s)).strip()


def solo_digitos(s):
    return re.sub(r"\D", "", s or "")


# Particulas con las que empiezan muchos municipios. Sirven para volver a separar
# nombres que el OCR pego ("SANVICENTEDELCAGUAN").
_PARTICULAS = ("SANTA", "SAN", "PUERTO", "LOS", "LAS", "EL", "LA")

# Municipios que empiezan igual que una particula pero se escriben de una sola
# pieza. Sin esta lista quedarian partidos ("SANTANDER" -> "SAN TANDER").
_UNA_SOLA_PALABRA = {"SANTANDER", "SANTIAGO", "SANTUARIO", "SANDONA",
                     "SANTACRUZ", "LABRANZAGRANDE", "LAGUNA", "ELIAS"}

# Cortar por DE o DEL adentro de la palabra es delicado: "CANDELARIA" tiene un
# "DEL" en la mitad. Solo se intenta en nombres muy largos, donde es casi seguro
# que el OCR pego varias palabras.
_LARGO_PARA_ENLACES = 14

# Los 32 departamentos y Bogota. En la cedula el lugar de nacimiento viene como
# "MILAN (CAQUETA)", pero el OCR a veces se come el parentesis y queda todo
# pegado; con esta lista se puede separar igual.
DEPARTAMENTOS = (
    "AMAZONAS", "ANTIOQUIA", "ARAUCA", "ATLANTICO", "BOLIVAR", "BOYACA",
    "CALDAS", "CAQUETA", "CASANARE", "CAUCA", "CESAR", "CHOCO", "CORDOBA",
    "CUNDINAMARCA", "GUAINIA", "GUAVIARE", "HUILA", "LA GUAJIRA", "GUAJIRA",
    "MAGDALENA", "META", "NARIÑO", "NORTE DE SANTANDER", "PUTUMAYO", "QUINDIO",
    "RISARALDA", "SAN ANDRES Y PROVIDENCIA", "SAN ANDRES", "SANTANDER", "SUCRE",
    "TOLIMA", "VALLE DEL CAUCA", "VALLE", "VAUPES", "VICHADA", "BOGOTA DC",
)


def separar_departamento(texto):
    """Parte "MILAN CAQUETA" en ("MILAN", "CAQUETA").

    Se compara con tolerancia para que un departamento mal leido ("CAOUETA")
    tambien se reconozca, pero se devuelve el texto tal como lo leyo el OCR:
    corregirlo es decision de quien revisa.
    """
    tokens = normalizar(texto).split()
    for n in (3, 2, 1):
        if len(tokens) > n:
            cola = " ".join(tokens[-n:])
            if any(parecido(cola, d) >= 0.85 for d in DEPARTAMENTOS):
                return " ".join(tokens[:-n]), cola
    return " ".join(tokens), ""


def separar_particulas(s):
    """Le devuelve los espacios a un nombre de municipio que quedo pegado.

    Solo toca cadenas largas y sin espacios, y solo corta por particulas
    conocidas, para no dañar municipios que de verdad se escriben en una sola
    palabra (VILLAGARZON, FLORENCIA, CANDELARIA).
    """
    t = normalizar(s)
    if " " in t or len(t) < 8 or t in _UNA_SOLA_PALABRA:
        return t

    largo = len(t)
    for p in _PARTICULAS:
        if t.startswith(p) and largo - len(p) >= 4:
            t = p + " " + t[len(p):]
            break

    if largo >= _LARGO_PARA_ENLACES:
        # "VICENTEDELCAGUAN" -> "VICENTE DEL CAGUAN"
        for enlace in ("DEL", "DE"):
            t = re.sub(rf"(?<=[A-ZÑ]{{3}}){enlace}(?=[A-ZÑ]{{3}})", f" {enlace} ", t)
    return re.sub(r"\s+", " ", t).strip()


# ------------------------------------------------------------------ etiquetas
# Cada etiqueta con las variantes que suele escupir el OCR.
ETIQUETAS = {
    "numero":    ["NUMERO", "NUMERO NUIP", "NUIP", "PRIMERA VEZ CC", "PRIMERA VEZ", "DUPLICADO CC", "RECTIFICACION CC"],
    "apellidos": ["APELLIDOS", "APELLDOS", "APELUDOS", "APELIDOS", "APELLIDO", "APELL", "APELDOS", "APELLIBCE", "ARELLIDOS", "APELLIDOS / NOMBRES", "APELLIDOS/NOMBRES", "APELLIDOS Y NOMBRES", "ARCLCOS NONB", "APELLIDOS NOMBR"],
    "nombres":   ["NOMBRES", "NOMBRE", "NOMORES", "NDMOAES", "NBMOAES", "NOMBAES", "NDMGRES", "OMORES", "NOMTRES", "NOMTRE", "NOMBR", "NOMBRES / APELLIDOS"],
    "ciudad":    ["LUGAR DE NACIMIENTO", "LUGARENAMENTO", "LUGARENACIMIENTO"],
    "expedicion":["FECHA Y LUGAR DE EXPEDICION", "FECHAYLGARDEEKPECON", "FECHA DE EXPEDICION", "LUGAR DE EXPEDICION"],
    "nacimiento":["FECHA DE NACIMIENTO", "FEOGENAOMIENTG", "FECHA NACIMIENTO"],
    "estatura":  ["ESTATURA", "ESTAGURA"],
    "sexo":      ["SEXO", "SEXD"],
    "rh":        ["GS RH", "GS", "RH", "G.S. RH", "G.S.RH"],
    "firma":     ["FIRMA", "FINMA"],
    "indice":    ["INDICE DERECHO", "INDICE", "NDICEEEFEC"],
    "vencimiento": ["FECHA DE VENCIMIENTO", "FECHA DE EXPIRACION", "FECHA DE EXPIRAC", "VALIDO HASTA EL"],
    "nacionalidad": ["NACIONALIDAD"],
}

# Etiquetas cuyo valor es una fecha. Se reparten juntas para que la fecha de
# vencimiento no termine puesta como fecha de nacimiento.
ETIQUETAS_FECHA = ("nacimiento", "expedicion", "vencimiento")

# Texto que nunca es un valor: encabezados, marcas de agua, etiquetas sueltas o deterioradas
_RUIDO = [
    "REPUBLICA DE COLOMBIA", "IDENTIFICACION PERSONAL", "CEDULA DE CIUDADANIA",
    "TARJETA DE IDENTIDAD", "IDENTIFICACION", "CONTRASENA", "CONTRASEÑA", "COMPROBANTE",
    "REGISTRADOR NACIONAL", "REGISTRADORA NACIONAL", "POWERED BY", "CAMSCANNER",
    "SCANNED WITH", "SCANNED WITH CAMSCANNER", "CS CAMSCANNER",
    "ESCANEADO CON CAMSCANNER", "ESCANEADO CON", "DOCUMENTO NO VALIDO",
    "REGISTRADURIA NACIONAL DEL ESTADO CIVIL", "ESTADO CIVIL",
    "CEDULA DE", "CIUDADANIA", "COL", "FIRMA", "INDICE DERECHO",
    "FECHAYLUOARDENACNENTO", "LUOARDENACNENTO", "FECHAYLUGARENACIMIENTO", "LUGARENACIMIENTO",
    "APELLIDOS / NOMBRES", "APELLIDOS Y NOMBRES", "ARCLCOS NONB", "ARCLCOS", "NONB",
    "IUNRO", "INRO", "NUMER", "NRO", "PEDICION", "EXPEDICION", "EXPEDIC", "EXPED"
]

# --------------------------------------------------------------- zona MRZ
# El reverso de la cedula nueva trae 3 renglones legibles por maquina (formato
# TD1). Es la fuente mas confiable: viene en monoespaciado y sin tildes.
#   ICCOL044330624844010<<<<<<<<<
#   0603283F3404104COL1117811433<9      <- nacimiento, sexo, vence, pais, NUIP
#   FIERRO<GUACA<<ERIKA<JULIANA<<<      <- apellidos<<nombres

#   AAMMDD nacimiento + digito + sexo + AAMMDD vence + digito + pais + NUIP
_RE_MRZ_DATOS = re.compile(r"^(\d{6})\d[MF<](\d{6})\d[A-Z0-9]{3}(\d{6,11})")
_RE_MRZ_NOMBRES = re.compile(r"^[A-Z<]{10,}$")

# Pie del reverso de la cedula amarilla. La cedula es el penultimo campo, justo
# antes de la fecha de expedicion:
#   P-4400100-01002886-F-1117553913-20180507
#   A-4401000-00241833-M-0079665698-20100618
#   P-44D0300-67115951-M1-0017711201-20030702
#
# Va impreso diminuto, asi que el OCR cambia digitos por letras parecidas
# ("1117810B09" por "1117810809"). Como el renglon completo tiene una forma tan
# marcada, se aceptan esas letras en el numero y se traducen de vuelta.
_RE_SERIAL = re.compile(r"-[MF]\d?-([0-9OQILBSZG]{6,11})-\d{8}(?!\d)")
_SERIAL_A_DIGITO = str.maketrans("OQILBSZG", "00118526")


def _plano(texto):
    """Sin espacios y en mayusculas, para leer renglones monoespaciados."""
    return re.sub(r"\s+", "", normalizar(texto))


_RE_SEPARADOR_MRZ = re.compile(r'[<KX]{1,3}')


def es_linea_mrz(texto):
    t = _plano(texto)
    if len(t) < 20:
        return False
    # Renglón de datos numéricos MRZ (nacimiento, sexo, vencimiento, NUIP)
    if _RE_MRZ_DATOS.search(t):
        return True
    # Renglón de cabecera TD1 (ej: ICCOL044330624844010<<<<<<<<<)
    if t.startswith(("ICCOL", "I<COL", "IDCOL", "IPCOL", "P<COL", "IC", "I<", "ID", "IP")) and "<" in t:
        return True
    # Renglón de nombres MRZ: debe tener al menos dos '<' reales y no ser una etiqueta de expedición/nacimiento
    if t.count("<") >= 2 and re.match(r'^[A-Z0-9<]+$', t) and not any(k in t for k in ("EXPEDIC", "NACIM", "LUGAR", "FECHA", "EXPED", "FECHAY", "PECION")):
        return True
    return False


def leer_mrz(lineas):
    """Saca documento, apellidos y nombres de la zona MRZ, si esta."""
    datos = {}
    for l in lineas:
        if not es_linea_mrz(l["texto"]):
            continue
        t = _plano(l["texto"])

        m = _RE_MRZ_DATOS.match(t)
        if m and "documento" not in datos:
            datos["documento"] = {"valor": m.group(3), "conf": l["conf"], "linea": l}
            nacio = fecha_de_mrz(m.group(1))
            if nacio:
                datos["nacimiento"] = {"valor": nacio, "conf": l["conf"], "linea": l}
            continue

        # Línea de nombres en MRZ: APELLIDOS<<NOMBRES con separador TD1 estándar (al menos dos < o <<)
        if "apellidos" not in datos and len(t) >= 15 and not any(char.isdigit() for char in t[:10]):
            t_util = re.sub(r"[<KX]+$", "", t)
            izq, der = "", ""
            if "<<" in t_util:
                izq, _, der = t_util.partition("<<")
            else:
                matches = [m for m in _RE_SEPARADOR_MRZ.finditer(t_util) if m.start() >= 3 and m.end() <= len(t_util) - 2]
                if matches:
                    # Preferir separadores de mayor longitud (ej: << o K< sobre < simple)
                    max_len = max(len(m.group(0)) for m in matches)
                    candidatos = [m for m in matches if len(m.group(0)) == max_len]
                    # Elegir el más cercano al centro de la línea útil
                    centro = len(t_util) / 2
                    mejor = min(candidatos, key=lambda m: abs((m.start() + m.end()) / 2 - centro))
                    izq = t_util[:mejor.start()]
                    der = t_util[mejor.end():]

            if izq and der:
                ape = re.sub(r"\s+", " ", re.sub(r"[<KX]", " ", izq)).strip()
                nom = re.sub(r"\s+", " ", re.sub(r"[<KX]", " ", der)).strip()
                if len(ape) >= 3:
                    datos["apellidos"] = {"valor": ape, "conf": l["conf"], "linea": l}
                if len(nom) >= 3:
                    datos["nombres"] = {"valor": nom, "conf": l["conf"], "linea": l}
    return datos


def leer_serial(lineas):
    """Saca la cedula del pie de codigo de barras del reverso amarillo."""
    for l in lineas:
        t = _plano(l["texto"])
        num = None
        m = _RE_SERIAL.search(t)
        if m:
            num = m.group(1).translate(_SERIAL_A_DIGITO)
            if not num.isdigit():
                num = None
        if num is None and "-" in t:
            partes = t.split("-")
            # Si termina en fecha de 8 dígitos ej -20100113 o -20180507
            if len(partes) >= 3 and len(partes[-1]) == 8 and partes[-1].isdigit():
                penultimo = partes[-2]
                m2 = re.search(r'([0-9OQILBSZG]{7,10})$', penultimo)
                if m2:
                    candidato = m2.group(1).translate(_SERIAL_A_DIGITO)
                    if candidato.isdigit():
                        num = candidato
            elif len(partes) >= 5 and re.fullmatch(r"\d{8}", partes[-1]):
                if partes[-2].isdigit():
                    num = partes[-2]
        if num:
            num = num.lstrip("0")
            if 6 <= len(num) <= 10:
                return {"valor": num, "conf": l["conf"], "linea": l}
    return None


def _es_etiqueta(texto, clave, umbral=0.80):
    c = compacto(texto)
    if not c:
        return False
    u = 0.58 if clave in ("expedicion", "ciudad", "vencimiento") else umbral
    return any(parecido(c, v) >= u for v in ETIQUETAS[clave])


def clasificar_etiqueta(texto, umbral=0.80):
    """Devuelve la clave de etiqueta que mejor coincide, o None."""
    c = compacto(texto)
    if len(c) < 2:
        return None
    mejor, mejor_r = None, 0.0
    for clave, variantes in ETIQUETAS.items():
        # Umbral adaptativo: las etiquetas largas como FECHA Y LUGAR DE EXPEDICION
        # acumulan mucho ruido de OCR y requieren un umbral más tolerante (0.58)
        u = 0.58 if clave in ("expedicion", "ciudad", "vencimiento") else umbral
        for v in variantes:
            r = parecido(c, v)
            if r >= u and r >= mejor_r:
                mejor, mejor_r = clave, r
    return mejor


def es_ruido(texto):
    c = compacto(texto)
    if not c:
        return True
    return any(parecido(c, r) >= 0.80 for r in _RUIDO)


# "REPUBLICA DE COLOMBIA" va estampado como marca de agua justo encima de los
# apellidos, y en un escaneo lavado es lo unico que queda legible ahi: sale como
# "JLOMBIA" o "COLOMPIA" y se cuela de apellido. Como nombre no sirve nunca; como
# lugar si, porque Colombia es un municipio del Huila.
_MARCA_AGUA = ("COLOMBIA", "REPUBLICA DE", "REPUBLICA")


def parece_nombre(texto, permitir_lugar=False):
    """Un valor de nombre/apellido: puras letras, al menos 3, sin ser etiqueta."""
    t = normalizar(texto)
    if not permitir_lugar and any(parecido(t, m) >= 0.80 for m in _MARCA_AGUA):
        return False
    if len(compacto(t)) < 3:
        return False
    if clasificar_etiqueta(t) or es_ruido(t):
        return False
    if "<" in t or es_linea_mrz(texto):
        return False
    # En estas hojas la gente anota al lado el correo y el telefono. Un correo
    # pasa por nombre sin problema ("Psanda mlena 6z@gmal.Con"), asi que se
    # descarta de una: ningun apellido lleva arroba.
    if "@" in t:
        return False
    letras = sum(c.isalpha() or c == "Ñ" for c in t)
    digitos = sum(c.isdigit() for c in t)
    # los nombres no llevan numeros; se tolera 1 caracter raro del OCR
    return letras >= 3 and digitos <= 1 and letras / max(len(t.replace(" ", "")), 1) > 0.7


_RE_FECHA = re.compile(
    r"\b\d{1,2}\s*[-/ ]?\s*(ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC)[A-Z]*\s*[-/ ]?\s*\d{4}\b"
)

# ---------------------------------------------------------------- fechas
# En la cedula las fechas van con el mes en letras ("01-DIC-2011", "10 ENE 1980").
# Se guardan siempre como DD/MM/AAAA, que es como las espera quien revisa.

MESES = ("ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT",
         "NOV", "DIC")

# El mes se captura tal cual y despues se busca a cual se parece, porque el OCR
# lo daña seguido ("DlC", "0CT", "SET"). Los separadores son opcionales: en la
# cedula nueva la fecha va tan apretada que sale de una pieza ("10ENE1980").
_RE_FECHA_LETRAS = re.compile(
    r"(\d{1,2})\s*[-/.,]?\s*([A-ZÑ0-9]{3,10})\s*[-/.,]?\s*(\d{4})")
_RE_FECHA_NUMEROS = re.compile(r"(\d{1,2})\s*[-/.]\s*(\d{1,2})\s*[-/.]\s*(\d{4})")

# Dentro del mes pasa al reves que en el resto de la fecha: el OCR mete digitos
# donde van letras ("0CT" por "OCT", "5EP" por "SEP").
_A_LETRA = str.maketrans("01458", "OIASB")


def _mes_numero(texto):
    """Numero de mes al que mas se parece un texto de 3 letras, o None."""
    t = compacto(texto)
    # sin al menos dos letras no es un mes sino un pedazo de numero suelto
    if sum(c.isalpha() for c in t) < 2:
        return None
    t = t.translate(_A_LETRA)[:3]
    mejor, mejor_r = None, 0.60
    for i, m in enumerate(MESES, 1):
        r = parecido(t, m)
        if r > mejor_r:
            mejor, mejor_r = i, r
    return mejor


# Dentro de una fecha el OCR cambia digitos por letras parecidas ("O5-MAY-2O1O").
# Solo se corrigen los pedazos que ya son casi todo numeros, para no tocar el mes
# ni el nombre del municipio que viene al lado.
_A_DIGITO = str.maketrans("OQIlSB", "001158")


def texto_para_fecha(s):
    """Normaliza el texto y le devuelve los digitos que el OCR volvio letras."""
    def arreglar(m):
        palabra = m.group(0)
        digitos = sum(c.isdigit() for c in palabra)
        if digitos and digitos >= len(palabra) - 2:
            return palabra.translate(_A_DIGITO)
        return palabra

    # la correccion es letra por letra, asi que las posiciones no se mueven
    return re.sub(r"[A-Z0-9]+", arreglar, normalizar(s))


def buscar_fecha(texto):
    """Primera fecha del texto. Devuelve (DD/MM/AAAA, inicio, fin) o None.

    Las posiciones son sobre texto_para_fecha(texto), que es del mismo largo que
    el texto normalizado.
    """
    t = texto_para_fecha(texto)
    for m in _RE_FECHA_LETRAS.finditer(t):
        mes = _mes_numero(m.group(2))
        if mes is None:
            continue
        dia, ano = int(m.group(1)), int(m.group(3))
        if 1 <= dia <= 31 and 1900 <= ano <= 2100:
            return f"{dia:02d}/{mes:02d}/{ano}", m.start(), m.end()
    m = _RE_FECHA_NUMEROS.search(t)
    if m:
        dia, mes, ano = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if 1 <= dia <= 31 and 1 <= mes <= 12 and 1900 <= ano <= 2100:
            return f"{dia:02d}/{mes:02d}/{ano}", m.start(), m.end()
    return None


def fecha_de_mrz(seis, futura=False):
    """Pasa un AAMMDD del MRZ a DD/MM/AAAA.

    El MRZ solo trae dos digitos de año. Una fecha de nacimiento no puede estar
    en el futuro, asi que 80 es 1980; una de vencimiento si, asi que 35 es 2035.
    """
    if not re.fullmatch(r"\d{6}", seis or ""):
        return ""
    aa, mm, dd = int(seis[:2]), int(seis[2:4]), int(seis[4:])
    if not (1 <= mm <= 12 and 1 <= dd <= 31):
        return ""
    from datetime import date
    ano = 2000 + aa
    if not futura and ano > date.today().year:
        ano -= 100
    return f"{dd:02d}/{mm:02d}/{ano}"


def calcular_edad(fecha, hoy=None):
    """Años cumplidos de una fecha DD/MM/AAAA hasta hoy. None si no se puede."""
    from datetime import date
    m = re.fullmatch(r"\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*", fecha or "")
    if not m:
        return None
    dia, mes, ano = (int(x) for x in m.groups())
    try:
        nacio = date(ano, mes, dia)
    except ValueError:
        return None
    hoy = hoy or date.today()
    if nacio > hoy:
        return None
    edad = hoy.year - nacio.year - ((hoy.month, hoy.day) < (nacio.month, nacio.day))
    return edad if 0 <= edad <= 120 else None


# --------------------------------------------------------- grupo sanguineo

RH_VALIDOS = ("O+", "O-", "A+", "A-", "B+", "B-", "AB+", "AB-")

# El OCR confunde el cero con la O ("0+" por "O+"). El signo NO se adivina: en un
# dato medico vale mas dejarlo vacio para que alguien lo escriba que poner un
# "+" donde iba un "-".
_RH_A_LETRA = str.maketrans("0", "O")


def normalizar_rh(texto):
    """Devuelve el grupo sanguineo ('O+', 'AB-') si el texto es uno, si no ''.

    Va impreso chiquito y pegado a su etiqueta, asi que el OCR lo entrega
    revuelto: '+0' por 'O+', 'AtH' por 'A+' con la RH de al lado encima. Se
    aceptan el desorden, la 't' que sale en vez de la cruz y la etiqueta pegada,
    pero el signo nunca se inventa: sin '+' ni '-' a la vista, se devuelve vacio.
    """
    t = re.sub(r"[^A-Z0-9+\-]", "", normalizar(texto)).translate(_RH_A_LETRA)
    t = t.replace("T", "+")            # la cruz suele salir como t
    t = re.sub(r"R?H$", "", t)         # "A+RH" / "AtH" -> "A+"
    m = re.fullmatch(r"(AB|[OAB])([+\-])|([+\-])(AB|[OAB])", t)
    if not m:
        return ""
    return (m.group(1) or m.group(4)) + (m.group(2) or m.group(3))


# ------------------------------------------------------- tipo de documento
# TI hasta los 17 años, CC desde los 18, CE, PEP, PPT, DNI, RC, Contraseña de Registraduría.
# Va impreso en el encabezado del frente o en el cuerpo del documento.

_TIPOS = (
    ("TI", ("TARJETA DE IDENTIDAD", "TARJETA IDENTIDAD", "IDENTIDAD", "TARJETA DE IDENTIFICACION")),
    ("CE", ("CEDULA DE EXTRANJERIA", "CEDULA EXTRANJERIA")),
    ("PPT", ("PERMISO POR PROTECCION TEMPORAL", "PROTECCION TEMPORAL", "PERMISO TEMPORAL")),
    ("PEP", ("PERMISO ESPECIAL DE PERMANENCIA", "ESPECIAL DE PERMANENCIA")),
    ("RC", ("REGISTRO CIVIL", "REGISTRO CIVIL DE NACIMIENTO")),
    ("DNI", ("DOCUMENTO NACIONAL DE IDENTIDAD", "DOCUMENTO DE IDENTIDAD")),
    ("CC", ("CEDULA DE CIUDADANIA", "CEDULA CIUDADANIA", "CIUDADANIA", "REPUBLICA DE COLOMBIA", "IDENTIFICACION PERSONAL", "COMPROBANTE DE DOCUMENTO EN TRAMITE", "CONTRASENA", "PRIMERA VEZ"))
)


def detectar_tipo_documento(lineas):
    # 1. Priorizar TI si trae TARJETA DE IDENTIDAD (o variaciones ruidosas como TARUETADEDENTHDAD)
    for l in lineas:
        c = compacto(l["texto"])
        if ("TARJETA" in c and "IDENTIDAD" in c) or ("TARJETADEIDENTIDAD" in c):
            return "TI", l
        if parecido(c, "TARJETADEIDENTIDAD") >= 0.70 or parecido(c, "TARJETAIDENTIDAD") >= 0.70:
            return "TI", l
        if ("TAR" in c or "TARI" in c or "TARU" in c) and ("DENTI" in c or "IDENT" in c or "TIDAD" in c):
            return "TI", l

    # 2. Cédula de Extranjería sólo si dice explícitamente EXTRANJERIA
    for l in lineas:
        c = compacto(l["texto"])
        if "EXTRANJERIA" in c:
            return "CE", l

    # 3. Clasificación por parecido con umbral estricto
    mejor = None
    for l in lineas:
        c = compacto(l["texto"])
        if len(c) < 6:
            continue

        for tipo, frases in _TIPOS:
            for f in frases:
                f_comp = compacto(f)
                r = parecido(c, f_comp)
                if r >= 0.82 and (mejor is None or r > mejor[0]):
                    mejor = (r, tipo, l)

    if mejor is None:
        return "CC", (lineas[0] if lineas else {"conf": 0.95, "x": 0, "y": 0, "w": 0, "h": 0})
    return mejor[1], mejor[2]


# Los puntos de miles del anverso ("1.110.461.846"). Ni el serial del borde ni
# los codigos de barras los usan, y la estatura ("1.55") no forma grupos de tres,
# asi que encontrar este patron en un renglon basta para saber que ahi va el
# numero, aunque venga con la etiqueta pegada.
_RE_MILES = re.compile(r"\d{1,3}(?:\.\d{3})+")


def _con_separador_de_miles(t):
    """Si el renglon trae el numero escrito en grupos de tres."""
    return bool(_RE_MILES.search(re.sub(r"\s+", "", t)))


def extraer_numero_documento(texto):
    """Devuelve el numero si la linea es un documento plausible, si no None."""
    t = normalizar(texto)

    # Descartar si es una fecha (ej: 19JUN2001, 17-JUN-2008, 19.SEP:2008, 20 FEB 2014, SOLITA)
    if re.search(r'\d{1,2}[\s\-\.:](?:ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC)[\s\-\.:]?\d{2,4}', t) or \
       re.search(r'\d{1,2}(?:ENE|FEB|MAR|ABR|MAY|JUN|JUL|AGO|SEP|OCT|NOV|DIC)\d{2,4}', t) or \
       re.search(r'(?:19|20)\d{2}(?:0[1-9]|1[0-2])(?:0[1-9]|[12]\d|3[01])', t):
        return None

    # Si contiene palabras de etiquetas como EXPEDICION, NACIMIENTO, VENCIMIENTO, no es número de cédula
    if any(k in compacto(t) for k in ["EXPEDIC", "NACIMIENTO", "VENCIM", "EXPIRAC"]):
        return None

    # Normalizar dos puntos o comas pegados a dígitos (ej: NUIP1:006.503.517 o 1,117,513,499)
    t_norm = re.sub(r'[,:]', '.', t)

    # 1. Puntos de miles o bloques con puntos (ej: 1.083.876.262, 83.232.810181, 1.117.513.499, :119.582444, 41119.582.444)
    m_puntos = re.search(r'([0-9]{1,4}(?:\.[0-9]{1,6})+)', t_norm)
    if m_puntos:
        # Extraer posibles dígitos previos pegados a letras o símbolos ej HEO1117499.735 o :119.582444
        idx_match = m_puntos.start()
        prefix_digs = re.search(r'([0-9]+)$', t_norm[:idx_match])
        pref = prefix_digs.group(1) if prefix_digs else ""
        d = pref + solo_digitos(m_puntos.group(1))
        # Corrección de OCR para el prefijo 1.119 / 1.117 cuando el primer 1 se pegó al dos puntos (ej: :119.582444 -> 1119582444)
        if len(d) == 9 and (d.startswith("119") or d.startswith("117") or d.startswith("118") or d.startswith("055")):
            d = "1" + d
        # Corrección de OCR si se coló un dígito espurio al final en cédulas de 10 dígitos (ej: 11175022457 -> 1117502245 o 1117502246)
        if len(d) == 11 and (d.startswith("111") or d.startswith("100") or d.startswith("108")):
            d = d[:10]
        if 7 <= len(d) <= 10 and not d.startswith("851"):
            return d

    # 2. Número tras etiqueta NUMERO / NUIP / CEDULA / PRIMERA VEZ CC (ej: PRIMERA VEZ CC 1.117.513.499)
    m_lbl = re.search(r'(?:NUMERO|NUMER|NUIP|CEDULA|IDENTIFICACION|PRIMERA\s*VEZ(?:\s*CC)?|DUPLICADO(?:\s*CC)?)\s*[:\.]?\s*([0-9\.\s]{6,14})', t_norm)
    if m_lbl:
        d = solo_digitos(m_lbl.group(1))
        if len(d) > 10 and d.startswith("0"):
            d = d.lstrip("0")
        if 7 <= len(d) <= 10 and not d.startswith("851"):
            return d

    # 3. Formato estándar dígitos sueltos
    t_clean = re.sub(r"^(NUMERO|NUMER|NUIP|CEDULA|PRIMERA\s*VEZ(?:\s*CC)?|DUPLICADO)\s*", "", t_norm)
    m_any = re.search(r'(?:^|[^\d])(1[0-9]{8,9}|[0-9]{7,8})(?:[^\d]|$)', t_clean)
    if m_any:
        d = m_any.group(1)
        if not d.startswith("4400") and not d.startswith("0100") and not d.startswith("851") and not d.startswith("201") and not d.startswith("202"):
            return d

    # Descartar seriales de control de reverso como 0061053220A1 o P-4400100...
    if re.search(r'\d+[A-Z]\s*\d+', t) or '-' in t:
        return None

    d = solo_digitos(t_clean)
    if len(d) == 11 and (d.startswith("111") or d.startswith("100") or d.startswith("108")):
        d = d[:10]
    elif len(d) > 10 and d.startswith("0"):
        d = d.lstrip("0")
    if 7 <= len(d) <= 10 and not d.startswith("0") and not d.startswith("851") and not d.startswith("201") and not d.startswith("202"):
        return d
    return None


# ------------------------------------------------------------- geometria

def _centro_y(l):
    return l["y"] + l["h"] / 2


def _centro_x(l):
    return l["x"] + l["w"] / 2


def _solapa_x(a, b):
    """Fraccion de solape horizontal entre dos lineas (0 a 1)."""
    ini = max(a["x"], b["x"])
    fin = min(a["x"] + a["w"], b["x"] + b["w"])
    if fin <= ini:
        return 0.0
    return (fin - ini) / max(min(a["w"], b["w"]), 1)


def _vecino(lineas, etiqueta, direccion, alto_pagina, max_saltos=2):
    """Lineas cercanas por encima (-1) o por debajo (+1) de una etiqueta,
    alineadas horizontalmente con ella. Devuelve las mas proximas primero."""
    limite = alto_pagina * 0.10          # no mirar mas alla del 10% de la pagina
    cand = []
    for l in lineas:
        if l is etiqueta:
            continue
        dy = (_centro_y(l) - _centro_y(etiqueta)) * direccion
        if dy <= 0 or dy > limite:
            continue
        if _solapa_x(l, etiqueta) < 0.15 and abs(l["x"] - etiqueta["x"]) > etiqueta["h"] * 3:
            continue
        cand.append((dy, l))
    cand.sort(key=lambda t: t[0])
    return [l for _, l in cand[:max_saltos]]


def detectar_formato(lineas):
    """'nueva', 'contrasena' o 'amarilla'. Decide por el NUIP, palabras clave o minusculas."""
    for l in lineas:
        c = compacto(l["texto"])
        if "CONTRASENA" in c or "COMPROBANTE" in c or "PRIMERAVEZ" in c:
            return "contrasena"
        if "NUIP" in c:
            return "nueva"
    # en la cedula nueva las etiquetas estan en minuscula ("Apellidos", "Nombres")
    minusculas = 0
    for l in lineas:
        if clasificar_etiqueta(l["texto"]) and re.search(r"[a-z]", l["texto"]):
            minusculas += 1
    return "nueva" if minusculas >= 2 else "amarilla"


# --------------------------------------------------------------- extraccion

def _valor_junto_a(lineas, etiqueta, direccion, alto, validador, max_lineas=1):
    """Toma hasta max_lineas de valor al lado de la etiqueta, en la direccion dada."""
    piezas = []
    for cand in _vecino(lineas, etiqueta, direccion, alto, max_saltos=max_lineas + 1):
        if clasificar_etiqueta(cand["texto"]) or es_ruido(cand["texto"]):
            break
        if not validador(cand["texto"]):
            break
        piezas.append(cand)
        if len(piezas) >= max_lineas:
            break
    return piezas


def _caja(l):
    return {"x": l["x"], "y": l["y"], "w": l["w"], "h": l["h"]}


def _marcar_firmas(lineas, alto):
    """La firma del Registrador Nacional trae su nombre debajo y el OCR la lee
    como si fuera un nombre. Se descartan esas lineas."""
    fuera = set()
    marcas = [l for l in lineas if parecido(l["texto"], "REGISTRADOR NACIONAL") >= 0.78
              or parecido(l["texto"], "REGISTRADORA NACIONAL") >= 0.78]
    for m in marcas:
        fuera.add(id(m))
        for l in lineas:
            if l is m:
                continue
            dy = _centro_y(l) - _centro_y(m)
            if 0 < dy < alto * 0.05 and _solapa_x(l, m) > 0.2:
                fuera.add(id(l))
    return fuera


def repartir_fechas(lineas, etiquetas, alto):
    """Asigna cada renglon que trae una fecha a su etiqueta correspondiente.

    Aqui no sirve mirar siempre para el mismo lado: en la cedula amarilla la
    fecha va a la DERECHA de su etiqueta y en el mismo renglon, en la nueva va
    debajo, y a veces el OCR junta etiqueta y valor en una sola linea. Por eso se
    reparte por cercania, y las tres etiquetas de fecha compiten a la vez para
    que la de vencimiento no termine ocupando el lugar de la de nacimiento.

    Devuelve {clave: {'fecha', 'resto', 'linea'}}.
    """
    presentes = {k: etiquetas[k] for k in ETIQUETAS_FECHA if k in etiquetas}

    hallados = []
    for l in lineas:
        hit = buscar_fecha(l["texto"])
        if not hit:
            continue
        fecha, ini, fin = hit
        t = texto_para_fecha(l["texto"])
        resto = (t[:ini] + " " + t[fin:]).strip()

        # La etiqueta puede venir pegada al valor en el mismo renglon. Se buscan
        # todas y no solo las que quedaron en 'etiquetas', porque justamente esas
        # lineas mezcladas no se reconocen como etiqueta: "FECHA DE NACIMIENTO
        # 04-DIC-2000" se parece un 0.79 a "FECHA DE NACIMIENTO", y no alcanza.
        propia, mejor_r = None, 0.72
        if resto:
            for k in ETIQUETAS_FECHA:
                r = max(parecido(resto, v) for v in ETIQUETAS[k])
                if r > mejor_r:
                    propia, mejor_r = k, r
        hallados.append((propia, fecha, resto, l))

    mejores = {}

    def proponer(clave, fecha, resto, linea, distancia):
        previo = mejores.get(clave)
        if previo is None or distancia < previo["distancia"]:
            mejores[clave] = {"fecha": fecha, "resto": resto, "linea": linea,
                              "distancia": distancia}

    for propia, fecha, resto, l in hallados:
        if propia:
            # Si la etiqueta esta en el mismo renglon no hay nada que adivinar.
            # Lo que sobre despues de quitarle las palabras de la etiqueta es el
            # lugar: "FECHA Y LUGAR DE EXPEDICION 28-ENE-2019 PUERTO RICO".
            texto_et = max(ETIQUETAS[propia], key=lambda v: parecido(resto, v))
            palabras = set(texto_et.split())
            sobra = " ".join(w for w in resto.split() if w not in palabras)
            proponer(propia, fecha, sobra, l, -1)
            continue
        elegida, menor = None, None
        for k, et in presentes.items():
            dy = abs(_centro_y(l) - _centro_y(et))
            if dy > alto * 0.06:
                continue
            # pesa mucho mas ir en la misma fila que estar cerca de lado
            d = dy * 4 + abs(_centro_x(l) - _centro_x(et))
            if menor is None or d < menor:
                elegida, menor = k, d
        if elegida:
            proponer(elegida, fecha, resto, l, menor)
        elif parece_nombre(resto):
            # "08 OCT 2001, PALERMO": una fecha con un municipio pegado al lado
            # solo puede ser la de expedicion. Ni la de nacimiento ni la de
            # vencimiento llevan lugar. Va con distancia enorme para que
            # cualquier asignacion hecha por etiqueta le gane.
            proponer("expedicion", fecha, resto, l, 10 ** 6)

    for v in mejores.values():
        v.pop("distancia", None)
    return mejores


def analizar_pagina(lineas, ancho, alto):
    """Saca los campos de una pagina ya pasada por OCR.

    Devuelve un dict con cada campo: {'valor', 'conf', 'origen', 'fuente'} donde
    'origen' son las cajas de las lineas usadas, para resaltarlas en pantalla, y
    'fuente' dice de donde salio el dato (frente, mrz o serial).
    """
    lineas = [l for l in lineas if l["texto"].strip()]

    mrz = leer_mrz(lineas)
    serial = leer_serial(lineas)
    formato = "nueva" if mrz else detectar_formato(lineas)
    # en la amarilla el valor esta ARRIBA de la etiqueta; en la nueva, ABAJO
    dir_valor = -1 if formato == "amarilla" else +1

    descartadas = _marcar_firmas(lineas, alto)
    utiles = [l for l in lineas if id(l) not in descartadas and not es_linea_mrz(l["texto"])]

    etiquetas = {}
    for l in utiles:
        clave = clasificar_etiqueta(l["texto"])
        if clave and clave not in etiquetas:
            etiquetas[clave] = l

    campos = {}

    def guardar(nombre, piezas, fuente="frente"):
        if not piezas:
            return
        piezas = sorted(piezas, key=_centro_y)   # de arriba hacia abajo
        texto = " ".join(p["texto"] for p in piezas)
        campos[nombre] = {
            "valor": limpiar_texto(texto),
            "conf": round(min(p["conf"] for p in piezas), 3),
            "origen": [_caja(p) for p in piezas],
            "fuente": fuente,
        }

    # --- numero de documento -------------------------------------------------
    # Compiten hasta tres lecturas: la del anverso, la del MRZ y la del pie de
    # barras. Se quedan todas anotadas para poder revisarlas si no concuerdan.
    lecturas = []

    # Altura tipica de renglon en esta pagina, para saber que texto va en grande.
    alturas = sorted(l["h"] for l in utiles)
    media_alto = alturas[len(alturas) // 2] if alturas else 1

    candidatos = []
    for l in utiles:
        num = extraer_numero_documento(l["texto"])
        if not num:
            continue
        # Vale como dato del anverso si esta pegado a la etiqueta NUMERO o NUIP.
        # Si no hay etiqueta (pasa en escaneos flojos) sirve el tamano: la cedula
        # va impresa en grande y el serial del borde en letra diminuta.
        anclado = "NUIP" in compacto(l["texto"])
        if not anclado and "numero" in etiquetas:
            anclado = abs(_centro_y(l) - _centro_y(etiquetas["numero"])) < alto * 0.06
        limpio = re.sub(r"^(NUMERO|NUIP)\s*", "", normalizar(l["texto"]))
        if anclado or _con_separador_de_miles(limpio):
            fuente = "frente"
        elif l["h"] >= media_alto * 1.3:
            fuente = "grande"
        else:
            fuente = "suelto"
        puntos = l["conf"] + CONFIABILIDAD.get(fuente, 1)
        # los de 8 y 10 digitos son los formatos tipicos de cedula
        if len(num) in (8, 10):
            puntos += 0.3
        candidatos.append((puntos, fuente, num, l))
    if candidatos:
        candidatos.sort(key=lambda t: -t[0])
        _, fuente, num, l = candidatos[0]
        lecturas.append({"valor": num, "conf": round(l["conf"], 3),
                         "origen": [_caja(l)], "fuente": fuente})

    if mrz.get("documento"):
        d = mrz["documento"]
        lecturas.append({"valor": d["valor"], "conf": round(d["conf"], 3),
                         "origen": [_caja(d["linea"])], "fuente": "mrz"})
    if serial:
        lecturas.append({"valor": serial["valor"], "conf": round(serial["conf"], 3),
                         "origen": [_caja(serial["linea"])], "fuente": "serial"})

    if lecturas:
        gana = lecturas[0]
        descartadas_doc = []
        for otra in lecturas[1:]:
            gana, pierde = preferir(gana, otra, "documento")
            if pierde:
                descartadas_doc.append(pierde["valor"])
        campos["documento"] = dict(gana)
        otras = sorted({v for v in descartadas_doc if compacto(v) != compacto(gana["valor"])})
        if otras:
            campos["documento"]["alternativas"] = otras

    # --- ciudad y departamento -----------------------------------------------
    piezas = []
    if "ciudad" in etiquetas:
        # ocupa 1 o 2 lineas: "SAN VICENTE DEL CAGUAN" + "(CAQUETA)"
        piezas = _valor_junto_a(utiles, etiquetas["ciudad"], dir_valor, alto,
                                lambda t: (parece_nombre(t, permitir_lugar=True)
                                           or t.strip().startswith("(")),
                                max_lineas=2)
    if not piezas and "expedicion" in etiquetas:
        # respaldo: "04-ABR-2003 CARTAGENA DE CHAIRA" -> quitarle la fecha
        crudas = _valor_junto_a(utiles, etiquetas["expedicion"], dir_valor, alto,
                                lambda t: bool(_RE_FECHA.search(normalizar(t))) or parece_nombre(t))
        for p in crudas:
            limpio = _RE_FECHA.sub(" ", normalizar(p["texto"])).replace(",", " ")
            if compacto(limpio):
                piezas.append(dict(p, texto=limpio))

    if piezas:
        piezas = sorted(piezas, key=_centro_y)
        texto = " ".join(p["texto"] for p in piezas)
        fuente = "reverso" if formato == "amarilla" else "frente"

        # El departamento va entre parentesis: "MILAN (CAQUETA)". Si el OCR los
        # perdio, se separa reconociendo el nombre del departamento al final.
        m = re.search(r"\(([^)]{3,})\)", texto)
        if m:
            ciudad_txt = texto[:m.start()] + " " + texto[m.end():]
            depto_txt = m.group(1)
        else:
            ciudad_txt, depto_txt = separar_departamento(texto)

        cajas = [_caja(p) for p in piezas]
        conf = round(min(p["conf"] for p in piezas), 3)
        ciudad_txt = separar_particulas(limpiar_texto(ciudad_txt))
        if ciudad_txt:
            campos["ciudad"] = {"valor": ciudad_txt, "conf": conf,
                                "origen": cajas, "fuente": fuente}
        if depto_txt:
            campos["departamento"] = {"valor": limpiar_texto(depto_txt), "conf": conf,
                                      "origen": cajas, "fuente": fuente}

    # --- apellidos y nombres -------------------------------------------------
    if formato == "contrasena":
        # En la contraseña la foto está a la izquierda y debajo van exactamente dos líneas:
        # Línea 1: APELLIDOS (ej: ESCOBAR MURIEL)
        # Línea 2: NOMBRES (ej: YHORLAN ERLENDY)
        lineas_bloque = []
        for l in utiles:
            c = compacto(l["texto"])
            if len(c) < 3 or clasificar_etiqueta(l["texto"]) or es_ruido(l["texto"]):
                continue
            if any(k in c for k in ["FECHA", "LUGAR", "EXPEDIC", "MASCULINO", "FEMENINO", "REGISTRADURIA", "APEL", "NONB", "ARCL"]):
                continue
            if parece_nombre(l["texto"]) or re.match(r'^[A-ZÑÁÉÍÓÚ\s]{3,35}$', normalizar(l["texto"])):
                lineas_bloque.append(l)

        lineas_bloque.sort(key=_centro_y)
        if len(lineas_bloque) >= 2:
            guardar("apellidos", [lineas_bloque[0]])
            guardar("nombres", [lineas_bloque[1]])
        elif len(lineas_bloque) == 1:
            partes = lineas_bloque[0]["texto"].split()
            if len(partes) >= 3:
                guardar("apellidos", [dict(lineas_bloque[0], texto=" ".join(partes[:2]))])
                guardar("nombres", [dict(lineas_bloque[0], texto=" ".join(partes[2:]))])
            else:
                guardar("apellidos", [lineas_bloque[0]])
    else:
        for campo in ("apellidos", "nombres"):
            if campo in etiquetas:
                piezas = _valor_junto_a(utiles, etiquetas[campo], dir_valor, alto, parece_nombre)
                guardar(campo, piezas)

        # En cédula amarilla, APELLIDOS va arriba (-1) y NOMBRES va abajo (+1) de la etiqueta APELLIDOS.
        # Si vino la etiqueta APELLIDOS pero faltó la de NOMBRES (ej: Sandra Milena), se toma la línea de abajo.
        if formato == "amarilla" and "apellidos" in etiquetas and "nombres" not in etiquetas and "nombres" not in campos:
            vecinos_abajo = _vecino(utiles, etiquetas["apellidos"], 1, alto, max_saltos=2)
            cands_nom = [v for v in vecinos_abajo if (parece_nombre(v["texto"]) or re.match(r'^[A-ZÑÁÉÍÓÚ\s]{3,35}$', normalizar(v["texto"]))) and not clasificar_etiqueta(v["texto"]) and not es_ruido(v["texto"])]
            if cands_nom:
                guardar("nombres", [cands_nom[0]])

        # Si faltó la etiqueta, se deduce por orden vertical
        tiene_serial_reverso = bool(mrz or serial)
        if ("apellidos" not in campos or "nombres" not in campos) and not tiene_serial_reverso:
            _rescatar_nombres(utiles, etiquetas, campos, alto, formato, guardar)

    # El MRZ sirve sobre todo para recuperar los espacios que el frente se come.
    for campo in ("apellidos", "nombres"):
        if mrz.get(campo):
            d = mrz[campo]
            desde_mrz = {"valor": limpiar_texto(d["valor"]), "conf": round(d["conf"], 3),
                         "origen": [_caja(d["linea"])], "fuente": "mrz"}
            gana, pierde = preferir(campos.get(campo), desde_mrz, campo)
            campos[campo] = dict(gana)
            if pierde and compacto(pierde["valor"]) != compacto(gana["valor"]):
                campos[campo]["alternativas"] = [pierde["valor"]]
        else:
            # Caso sin respaldo de MRZ: si el nombre o apellido del frente sale como una sola
            # palabra pegada (ej: ALDANABOHORQUEZ) y no hubo MRZ que lo contrastara:
            # se baja conf a 0.3 y se agrega la marca revisar=True
            val_actual = (campos.get(campo) or {}).get("valor", "")
            if val_actual and " " not in val_actual.strip() and len(val_actual.strip()) >= 10:
                campos[campo]["conf"] = 0.3
                campos[campo]["revisar"] = True

    # Los datos que siguen van todos en el dorso de la cedula amarilla, y en el
    # mismo frente de la nueva.
    fuente_dorso = "reverso" if formato == "amarilla" else "frente"

    # --- grupo sanguineo (G.S. RH) -------------------------------------------
    linea_rh = None
    if "rh" in etiquetas:
        junto = _valor_junto_a(utiles, etiquetas["rh"], dir_valor, alto,
                               lambda t: bool(normalizar_rh(t)))
        if junto:
            linea_rh = junto[0]
    if linea_rh is None:
        # Respaldo para cuando no se leyo la etiqueta, que en la cedula nueva va
        # en letra diminuta: "O+" o "AB-" son cadenas tan particulares que no se
        # confunden con nada mas de la cedula, asi que sirve hallarlas sueltas.
        sueltas = [l for l in utiles if normalizar_rh(l["texto"])]
        if sueltas:
            linea_rh = max(sueltas, key=lambda l: l["conf"])
    if linea_rh is not None:
        campos["rh"] = {
            "valor": normalizar_rh(linea_rh["texto"]),
            "conf": round(linea_rh["conf"], 3),
            "origen": [_caja(linea_rh)], "fuente": fuente_dorso,
        }

    # --- tipo de documento (TI / CC / CE / PPT / PEP) -----------------------
    hallado = detectar_tipo_documento(lineas)
    if hallado:
        tipo, linea_tipo = hallado
        campos["tipo_documento"] = {
            "valor": tipo, "conf": round(linea_tipo["conf"], 3),
            "origen": [_caja(linea_tipo)], "fuente": "frente",
        }
    elif formato == "contrasena":
        campos["tipo_documento"] = {
            "valor": "CC", "conf": 0.95, "origen": [], "fuente": "frente"
        }

    # Si hay fecha de nacimiento y es menor de 18 años (nacido de 2007 en adelante), es TI por ley colombiana
    if "nacimiento" in campos:
        edad_calculada = calcular_edad(campos["nacimiento"]["valor"])
        if edad_calculada is not None and edad_calculada < 18:
            if campos.get("tipo_documento", {}).get("valor") in ("CC", None):
                campos["tipo_documento"] = {
                    "valor": "TI", "conf": 0.98,
                    "origen": campos.get("tipo_documento", {}).get("origen", []),
                    "fuente": "frente"
                }

    # --- fechas y lugar de expedicion ----------------------------------------
    fechas = repartir_fechas(utiles, etiquetas, alto)

    for clave in ("nacimiento", "expedicion"):
        hallado = fechas.get(clave)
        if not hallado:
            continue
        campos[clave] = {
            "valor": hallado["fecha"], "conf": round(hallado["linea"]["conf"], 3),
            "origen": [_caja(hallado["linea"])], "fuente": fuente_dorso,
        }

    # "28-ENE-2019 PUERTO RICO": lo que queda al quitarle la fecha es el lugar
    if fechas.get("expedicion"):
        linea_exp = fechas["expedicion"]["linea"]
        lugar = separar_particulas(limpiar_texto(fechas["expedicion"]["resto"]))
        if len(compacto(lugar)) >= 3 and not es_ruido(lugar):
            campos["lugar_expedicion"] = {
                "valor": lugar, "conf": round(linea_exp["conf"], 3),
                "origen": [_caja(linea_exp)], "fuente": fuente_dorso,
            }

    # El MRZ trae la fecha de nacimiento en digitos y sin tildes; cuando esta,
    # compite de igual a igual con la impresa y la que pierda queda anotada.
    if mrz.get("nacimiento"):
        d = mrz["nacimiento"]
        desde_mrz = {"valor": d["valor"], "conf": round(d["conf"], 3),
                     "origen": [_caja(d["linea"])], "fuente": "mrz"}
        gana, pierde = preferir(campos.get("nacimiento"), desde_mrz, "nacimiento")
        campos["nacimiento"] = dict(gana)
        if pierde and compacto(pierde["valor"]) != compacto(gana["valor"]):
            campos["nacimiento"]["alternativas"] = [pierde["valor"]]

    campos["_formato"] = formato
    campos["_lados"] = _detectar_lados(etiquetas, campos, bool(mrz or serial))
    return campos


def _rescatar_nombres(lineas, etiquetas, campos, alto, formato, guardar):
    """Cuando el OCR no leyó la etiqueta APELLIDOS o NOMBRES, o en contraseñas donde vienen
    en bloque secuencial, asigna en orden vertical: APELLIDOS (primera línea) y NOMBRES (segunda línea)."""
    ancla_arriba = etiquetas.get("numero")
    if not ancla_arriba:
        doc = campos.get("documento") or {}
        if doc.get("fuente") in ("frente", "grande") and doc.get("origen"):
            o = doc["origen"][0]
            ancla_arriba = {"y": o["y"], "h": o["h"], "x": o["x"], "w": o["w"]}
    
    tope = _centro_y(ancla_arriba) if ancla_arriba else 0
    fondo = tope + alto * 0.50 if ancla_arriba else alto * 0.35

    for clave in ("firma", "nacionalidad", "nacimiento", "expedicion", "ciudad"):
        if clave in etiquetas and _centro_y(etiquetas[clave]) > tope:
            fondo = min(fondo, _centro_y(etiquetas[clave]))

    if formato == "amarilla" and "nombres" in etiquetas:
        fondo = min(fondo, _centro_y(etiquetas["nombres"]))

    usados = set()
    for c in campos.values():
        if isinstance(c, dict):
            for o in c.get("origen", []):
                usados.add((o["x"], o["y"]))

    libres = [
        l for l in lineas
        if (tope <= _centro_y(l) <= fondo or not ancla_arriba)
        and (parece_nombre(l["texto"]) or re.match(r'^[A-ZÑÁÉÍÓÚ\s]{3,35}$', normalizar(l["texto"])))
        and not es_ruido(l["texto"])
        and not clasificar_etiqueta(l["texto"])
        and len(compacto(l["texto"])) >= 4
        and not any(r in compacto(l["texto"]) for r in ["FECHAY", "LUOARD", "ARCLCO", "IUNRO", "NONB"])
        and (l["x"], l["y"]) not in usados
    ]
    libres.sort(key=_centro_y)

    if ("apellidos" not in campos or "nombres" not in campos) and len(libres) >= 2:
        if "apellidos" not in campos:
            guardar("apellidos", [libres[0]])
        if "nombres" not in campos:
            guardar("nombres", [libres[1]])
    elif len(libres) == 1:
        partes = libres[0]["texto"].split()
        if len(partes) >= 3 and "apellidos" not in campos and "nombres" not in campos:
            guardar("apellidos", [dict(libres[0], texto=" ".join(partes[:2]))])
            guardar("nombres", [dict(libres[0], texto=" ".join(partes[2:]))])
        elif "apellidos" not in campos:
            guardar("apellidos", [libres[0]])
        elif "nombres" not in campos:
            guardar("nombres", [libres[0]])


def _detectar_lados(etiquetas, campos, hay_mrz_o_serial=False):
    """Que caras de la cedula trae la pagina. El frente solo cuenta si vino de
    las etiquetas del anverso, no del MRZ ni del codigo de barras."""
    frente = "numero" in etiquetas or any(
        (campos.get(k) or {}).get("fuente") == "frente" for k in ("apellidos", "nombres"))
    reverso = hay_mrz_o_serial or any(
        k in etiquetas for k in ("ciudad", "indice", "nacimiento", "expedicion"))
    if frente and reverso:
        return "ambos"
    if reverso:
        return "reverso"
    return "frente" if frente else "indefinido"
