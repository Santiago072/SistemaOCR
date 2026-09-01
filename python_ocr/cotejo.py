"""Agrupa las paginas por persona y las compara contra el listado de referencia."""

from campos import (calcular_edad, compacto, limpiar_texto, normalizar,
                    parecido, preferir)
from listado import separar_nombre

CAMPOS = ("tipo_documento", "documento", "apellidos", "nombres")

# Como se llama cada campo cuando hay que decirlo en una frase.
NOMBRE_CAMPO = {
    "tipo_documento": "tipo de documento",
    "documento": "número",
    "apellidos": "apellidos",
    "nombres": "nombres",
    "nacimiento": "fecha de nacimiento",
    "rh": "grupo sanguíneo",
    "ciudad": "ciudad",
    "departamento": "departamento",
    "expedicion": "fecha de expedición",
    "lugar_expedicion": "lugar de expedición",
}

# Por debajo de esto se considera que son personas distintas al cruzar por nombre.
UMBRAL_PARECIDO = 0.62


# ------------------------------------------------------------ agrupar paginas

def _doc(campos):
    return (campos.get("documento") or {}).get("valor", "")


def _es_solo_reverso(campos):
    """Detecta si una página es exclusivamente el reverso de una cédula y no un anverso con identidad propia."""
    # Si la página tiene nombres o apellidos propios, NUNCA es un reverso de otra persona
    if campos.get("nombres") or campos.get("apellidos"):
        return False
    
    # Reverso si tiene fecha de nacimiento, RH, lugar/ciudad de expedición, estatura o _lados es 'reverso'
    tiene_reverso = bool(
        campos.get("nacimiento") or 
        campos.get("rh") or 
        campos.get("ciudad") or 
        campos.get("lugar_expedicion") or 
        campos.get("departamento") or
        campos.get("_lados") == "reverso"
    )
    return tiene_reverso


def agrupar_paginas(paginas):
    """Junta frente y reverso de la misma cedula.

    'paginas' es [{'pagina': n, 'imagen': ruta, 'campos': {...}}] en el orden del
    archivo. Se agrupa por numero de documento, que el reverso tambien suele traer (en
    el MRZ de la cedula nueva o en el pie de barras de la amarilla). Si una pagina consecutiva
    es un reverso (o no tiene numero ni nombres nuevos), se fusiona con la persona anterior.
    """
    personas = []
    por_doc = {}
    actual = None

    for p in paginas:
        campos = p["campos"]
        doc = _doc(campos)
        tiene_nombres = bool(campos.get("apellidos") or campos.get("nombres"))
        es_reverso = _es_solo_reverso(campos)
        es_reverso_puro = es_reverso and not tiene_nombres
        formato = campos.get("_formato")
        lados = campos.get("_lados", "")

        if doc and doc in por_doc:
            # Caso 1: Coincidencia exacta de documento leído en ambas páginas (ej: MRZ o serial de reverso)
            destino = por_doc[doc]
        elif doc and actual is not None and actual.get("documento") == doc:
            destino = actual
        elif doc and actual is not None and actual.get("documento") and (
            (lados == "reverso" or campos.get("nacimiento") == (actual.get("campos", {}).get("nacimiento") or {}).get("valor")) or
            (not tiene_nombres) or
            (campos.get("apellidos", {}).get("valor") and campos.get("apellidos", {}).get("valor") == (actual.get("campos", {}).get("apellidos") or {}).get("valor"))
        ) and (
            parecido(doc, actual.get("documento")) >= 0.85 or
            (len(doc) == len(actual.get("documento")) and sum(c1 != c2 for c1, c2 in zip(doc, actual.get("documento"))) <= 2)
        ):
            # Caso 1.1: Documento leído en reverso difiere en 1-2 dígitos por ruido de OCR (ej: 1117265865 vs 1117266865) pero es la misma persona
            destino = actual
        elif es_reverso_puro and actual is not None and not doc and lados != "ambos":
            # Caso 2: Es un reverso puro sin documento que sigue a un anverso
            destino = actual
        elif not tiene_nombres and not doc and actual is not None and formato != "contrasena" and lados != "ambos" and lados != "frente":
            # Caso 3: Página vacía/blanca o huérfana de anverso
            destino = actual
        elif doc:
            # Caso 4: Página con número de documento (sea anverso o reverso independiente)
            destino = {"documento": doc, "paginas": [], "campos": {}}
            personas.append(destino)
            por_doc[doc] = destino
        else:
            # Caso 5: Anverso con nombres pero sin número de documento, o página independiente
            destino = {"documento": "", "paginas": [], "campos": {}}
            personas.append(destino)

        destino["paginas"].append(p)
        _fusionar(destino["campos"], campos, p["pagina"])
        actual = destino

    for per in personas:
        per["documento"] = (per["campos"].get("documento") or {}).get("valor", "")
        per["paginas"].sort(key=lambda p: p["pagina"])
    return personas


def _fusionar(destino, nuevos, npagina):
    """Mete los campos de una pagina en la persona. Lo que se descarta queda
    anotado como alternativa, para poder revisarlo en pantalla."""
    for clave, dato in nuevos.items():
        if clave.startswith("_") or not isinstance(dato, dict):
            continue
        dato = dict(dato, pagina=npagina)
        previo = destino.get(clave)
        if previo is None:
            destino[clave] = dato
            continue

        gana, pierde = preferir(previo, dato, clave)
        alts = set(previo.get("alternativas", [])) | set(dato.get("alternativas", []))
        if compacto(pierde["valor"]) != compacto(gana["valor"]):
            alts.add(pierde["valor"])
        gana = dict(gana)
        alts = {a for a in alts if compacto(a) != compacto(gana["valor"])}
        if alts:
            gana["alternativas"] = sorted(alts)
        else:
            gana.pop("alternativas", None)
        destino[clave] = gana


# --------------------------------------------------------- cruce con listado

def _similitud_nombre(a, b):
    """Compara nombres sin importar el orden de las palabras (el listado los da
    como NOMBRES + APELLIDOS y la cedula al reves)."""
    ta, tb = normalizar(a).split(), normalizar(b).split()
    if not ta or not tb:
        return 0.0
    directo = parecido(" ".join(ta), " ".join(tb))
    ordenado = parecido(" ".join(sorted(ta)), " ".join(sorted(tb)))
    return max(directo, ordenado)


def cruzar(personas, listado):
    """Empareja cada cedula con una fila del listado y marca las diferencias.

    Devuelve (personas_anotadas, sobrantes_del_listado).
    """
    por_doc = {p["documento"]: p for p in listado}
    libres = {p["documento"] for p in listado}

    # 1) por numero de documento exacto, que es lo mas confiable
    for per in personas:
        per["referencia"] = None
        per["origen_cruce"] = None
        doc_per = per["documento"]
        ref = por_doc.get(doc_per)
        if ref and ref["documento"] in libres:
            per["referencia"] = ref
            per["origen_cruce"] = "documento"
            libres.discard(ref["documento"])

    # 1.1) por similitud de documento muy alta (diferencia de 1 caracter por ruido OCR)
    for per in personas:
        if per["referencia"]:
            continue
        doc_per = per["documento"]
        if not doc_per or len(doc_per) < 6:
            continue
        mejor_ref, mejor_diff = None, 3
        for ref in listado:
            if ref["documento"] not in libres:
                continue
            doc_ref = ref["documento"]
            if len(doc_ref) == len(doc_per):
                diff = sum(c1 != c2 for c1, c2 in zip(doc_ref, doc_per))
                if diff <= 2 and diff < mejor_diff:
                    mejor_ref, mejor_diff = ref, diff
            elif abs(len(doc_ref) - len(doc_per)) <= 1:
                r_num = parecido(doc_per, doc_ref)
                if r_num >= 0.85:
                    mejor_ref, mejor_diff = ref, 1
        if mejor_ref:
            per["referencia"] = mejor_ref
            per["origen_cruce"] = "documento_aproximado"
            libres.discard(mejor_ref["documento"])

    # 2) los que quedaron sueltos, por parecido de nombre
    for per in personas:
        if per["referencia"]:
            continue
        nom_leido = (per['campos'].get('nombres') or {}).get('valor','')
        ape_leido = (per['campos'].get('apellidos') or {}).get('valor','')
        leido = f"{nom_leido} {ape_leido}".strip()
        if not compacto(leido):
            continue
        mejor, mejor_r = None, 0.45
        for ref in listado:
            if ref["documento"] not in libres:
                continue
            r = _similitud_nombre(leido, ref["nombre_completo"])
            if r > mejor_r:
                mejor, mejor_r = ref, r
        if mejor:
            per["referencia"] = mejor
            per["origen_cruce"] = "nombre"
            per["similitud_cruce"] = round(mejor_r, 3)
            if not per.get("documento") or per.get("documento") != mejor["documento"]:
                per["documento"] = mejor["documento"]
                if "documento" not in per["campos"] or not per["campos"]["documento"].get("valor"):
                    per["campos"]["documento"] = {"valor": mejor["documento"], "conf": 0.95, "origen": [], "fuente": "listado"}
            libres.discard(mejor["documento"])

    for per in personas:
        _comparar(per)

    sobrantes = [p for p in listado if p["documento"] in libres]
    return personas, sobrantes


def _comparar(per):
    """Calcula, campo por campo, que dice el OCR y que dice el listado."""
    ref = per["referencia"]
    campos = per["campos"]
    esperado = {c: "" for c in CAMPOS}

    if ref:
        esperado["documento"] = ref["documento"]
        # el listado del SENA trae una columna TI/CC que si se puede contrastar
        esperado["tipo_documento"] = ref.get("tipo", "")
        if ref["apellidos"] or ref["nombres"]:
            esperado["apellidos"] = ref["apellidos"]
            esperado["nombres"] = ref["nombres"]
        else:
            # el listado trae el nombre pegado: se parte usando lo que leyo el OCR
            pista = (campos.get("apellidos") or {}).get("valor", "")
            nom, ape = separar_nombre(ref["nombre_completo"], pista)
            esperado["nombres"], esperado["apellidos"] = nom, ape

    detalle = {}
    for c in CAMPOS:
        leido = (campos.get(c) or {}).get("valor", "")
        esp = esperado.get(c, "")
        if not esp:
            estado = "sin_referencia"
            sim = None
        elif not leido:
            # Si el documento coincide exactamente con el listado, se adopta el valor oficial
            estado = "igual" if ref else "no_leido"
            sim = 1.0 if ref else 0.0
        else:
            sim = parecido(leido, esp)
            if c in ("apellidos", "nombres"):
                sim_global = _similitud_nombre(f"{(campos.get('nombres') or {}).get('valor','')} {(campos.get('apellidos') or {}).get('valor','')}", ref["nombre_completo"]) if ref else sim
                if per.get("editado"):
                    es_igual = (compacto(leido) == compacto(esp)) or (sim is not None and sim >= 0.75) or (sim_global is not None and sim_global >= 0.80)
                else:
                    es_igual = bool(ref) or compacto(leido) == compacto(esp) or sim >= 0.65 or sim_global >= 0.65
            elif c == "tipo_documento":
                # Si el tipo difiere entre lo leído y lo esperado (ej: TI vs CC), marcar discrepancia para revisión
                if leido and esp and leido.upper() != esp.upper():
                    es_igual = False
                else:
                    es_igual = True
            elif c == "documento":
                if per.get("editado"):
                    es_igual = compacto(leido) == compacto(esp)
                else:
                    es_igual = compacto(leido) == compacto(esp) or bool(ref and per.get("origen_cruce") in ("nombre", "documento")) or (bool(ref) and parecido(leido, esp) >= 0.70)
            else:
                es_igual = compacto(leido) == compacto(esp)
            estado = "igual" if es_igual else "difiere"
        detalle[c] = {
            "leido": leido,
            "esperado": esp,
            "estado": estado,
            "similitud": None if sim is None else round(sim, 3),
            "conf_ocr": (campos.get(c) or {}).get("conf"),
            "revisar": (campos.get(c) or {}).get("revisar", False),
        }

    per["comparacion"] = detalle
    hay_discrepancia_doc = detalle.get("documento", {}).get("estado") == "difiere"
    hay_discrepancia_tipo = detalle.get("tipo_documento", {}).get("estado") == "difiere"
    hay_discrepancia_nom = detalle.get("nombres", {}).get("estado") == "difiere" or detalle.get("apellidos", {}).get("estado") == "difiere"

    if not ref:
        per["estado"] = "sin_listado"
    elif hay_discrepancia_doc or hay_discrepancia_tipo:
        per["estado"] = "revisar"
    elif hay_discrepancia_nom and per.get("origen_cruce") != "documento":
        per["estado"] = "revisar"
    else:
        per["estado"] = "ok"

    # Valores finales consolidados: Adoptar siempre el listado oficial limpio del Excel,
    # salvo cuando hay discrepancia de tipo de documento (mostrar el tipo real de la cédula subida).
    per["valores"] = {}
    for c in CAMPOS:
        d = detalle[c]
        if c == "tipo_documento" and d["estado"] == "difiere" and d["leido"]:
            mejor = d["leido"]
        else:
            mejor = d["esperado"] if (ref and d["esperado"]) else d["leido"]
        if c in ("apellidos", "nombres") and mejor:
            mejor = limpiar_texto(mejor)
        per["valores"][c] = mejor

    # De donde salio esta persona: del PDF y del listado, o solo del PDF. Las que
    # estan solo en el listado no llegan aca, salen como 'faltantes'.
    per["origen"] = "ambos" if ref else "solo_pdf"
    per["edad"] = calcular_edad(per["valores"].get("nacimiento", ""))
    per["aviso_edad"] = _aviso_edad(per["valores"].get("tipo_documento", ""), per["edad"])
    per["novedad"] = _novedad(per, detalle)


def _aviso_edad(tipo, edad):
    """La tarjeta de identidad es hasta los 17; a los 18 toca cedula. Si el tipo
    leido no cuadra con la edad, algo hay que mirar."""
    if edad is None or not tipo:
        return ""
    if tipo == "TI" and edad >= 18:
        return f"Es tarjeta de identidad pero ya tiene {edad} años"
    if tipo == "CC" and edad < 18:
        return f"Es cédula de ciudadanía pero tiene {edad} años"
    return ""


def recalcular_derivados(per):
    """Vuelve a sacar comparacion, estado, edad, aviso y novedad despues de una correccion a mano."""
    valores = per.get("valores", {})
    campos = per.get("campos", {})
    for c in CAMPOS:
        if c in valores:
            if c not in campos:
                campos[c] = {}
            campos[c]["valor"] = valores[c]
    _comparar(per)
    per["edad"] = calcular_edad(valores.get("nacimiento", ""))
    per["aviso_edad"] = _aviso_edad(valores.get("tipo_documento", ""), per["edad"])
    per["novedad"] = _novedad(per, per.get("comparacion", {}))


def _novedad(per, detalle):
    """Frase corta que resume en que quedo esta cedula."""
    if not per.get("referencia"):
        return "No aparece en el listado"

    difieren = [NOMBRE_CAMPO.get(c, c) for c, d in detalle.items() if d["estado"] == "difiere"]
    faltan = [NOMBRE_CAMPO.get(c, c) for c, d in detalle.items() if d["estado"] == "no_leido"]
    partes = []
    if difieren:
        partes.append("Difiere en " + ", ".join(difieren))
    if faltan:
        partes.append("No se leyó " + ", ".join(faltan))
    if per.get("aviso_edad"):
        partes.append(per["aviso_edad"])
    if partes:
        return "; ".join(partes)
    if per.get("origen_cruce") == "nombre":
        return "Cruzada por parecido de nombre, no por número"
    return "Datos verificados correctamente"
