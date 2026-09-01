"""La aplicacion web: recibe los archivos, los manda a leer y sirve la pantalla.

Se usa desde `escritorio.py` (ventana propia) o desde `python -m ocr_cedulas.servidor`
si se prefiere abrirla en el navegador.
"""
import hashlib
import io
import json
import os
import shutil
import subprocess
import threading
import traceback
import uuid
from datetime import datetime

from flask import (Flask, abort, jsonify, redirect, render_template, request,
                   send_file, send_from_directory, url_for)
from werkzeug.utils import secure_filename

try:
    import config, exportar, listado as mod_listado, ocr
    from campos import analizar_pagina
    from cotejo import CAMPOS, agrupar_paginas, cruzar, recalcular_derivados
except ImportError:
    import config, exportar, listado as mod_listado, ocr
    from campos import analizar_pagina
    from cotejo import CAMPOS, agrupar_paginas, cruzar, recalcular_derivados

app = Flask(__name__,
            template_folder=config.PLANTILLAS,
            static_folder=config.ESTATICOS,
            static_url_path="/estaticos")
app.config["MAX_CONTENT_LENGTH"] = config.TAMANO_MAXIMO
# sin cache: es una herramienta local y conviene que los cambios se vean al recargar
app.config["SEND_FILE_MAX_AGE_DEFAULT"] = 0
# si se edita una plantilla, que se recargue sin tener que reiniciar el programa
app.config["TEMPLATES_AUTO_RELOAD"] = True

# Avance de cada trabajo en curso. Se guarda en memoria; lo definitivo va al disco.
_avance = {}
_candado = threading.Lock()


# ------------------------------------------------------------------ utilidades

def _carpeta(trabajo):
    return os.path.join(config.DATOS, secure_filename(trabajo))


def _ruta_resultado(trabajo):
    return os.path.join(_carpeta(trabajo), "resultado.json")


def _poner_avance(trabajo, **datos):
    with _candado:
        _avance.setdefault(trabajo, {}).update(datos)


def _leer_resultado(trabajo):
    ruta = _ruta_resultado(trabajo)
    if not os.path.exists(ruta):
        return None
    with open(ruta, encoding="utf-8") as f:
        return json.load(f)


def _guardar_resultado(trabajo, datos):
    with open(_ruta_resultado(trabajo), "w", encoding="utf-8") as f:
        json.dump(datos, f, ensure_ascii=False)


def _huella(archivos):
    """Identifica el contenido subido, para reconocer un archivo ya procesado."""
    h = hashlib.sha256()
    for f in archivos:
        f.stream.seek(0)
        for trozo in iter(lambda: f.stream.read(1 << 20), b""):
            h.update(trozo)
        f.stream.seek(0)
    return h.hexdigest()


def _buscar_por_huella(huella):
    """Un trabajo anterior con exactamente el mismo contenido, si existe."""
    if not os.path.isdir(config.DATOS):
        return None
    for nombre in sorted(os.listdir(config.DATOS), reverse=True):
        datos = _leer_resultado(nombre)
        if datos and datos.get("huella") == huella:
            return nombre
    return None


@app.context_processor
def _ayudas_plantilla():
    """Agrega la fecha del archivo a la direccion del css y del js, para que el
    navegador no se quede con una version vieja guardada en cache."""
    def estatico(nombre):
        ruta = os.path.join(app.static_folder, nombre)
        v = int(os.path.getmtime(ruta)) if os.path.exists(ruta) else 0
        return url_for("static", filename=nombre, v=v)
    return {"estatico": estatico}


# --------------------------------------------------------------- procesamiento

def _armar_personas(paginas, gente):
    """Agrupa las paginas leidas hasta ahora y las cruza con el listado."""
    import copy
    paginas_limpias = copy.deepcopy(paginas)
    personas = agrupar_paginas(paginas_limpias)
    personas, faltantes = cruzar(personas, gente)
    for i, per in enumerate(personas):
        per["id"] = i
        per["editado"] = False
        per["revisada"] = False
        for p in per["paginas"]:      # el texto crudo no se necesita en pantalla
            p.pop("lineas", None)
    return personas, faltantes


def _procesar(trabajo, rutas_doc, ruta_listado, nombre_doc, nombre_listado, huella):
    """Corre en segundo plano: OCR, extraccion de campos y cruce con el listado."""
    carpeta = _carpeta(trabajo)
    try:
        gente = []
        if ruta_listado:
            _poner_avance(trabajo, etapa="listado", mensaje="Leyendo el listado...")
            gente = mod_listado.cargar(ruta_listado)

        def avisar(hecho, total):
            _poner_avance(trabajo, etapa="ocr", hecho=hecho - 1, total=total,
                          mensaje=f"Leyendo página {hecho} de {total}...")

        # Cada pagina que queda lista se interpreta de una y se publica, para que
        # se pueda ir revisando sin esperar a que termine el archivo completo.
        listas = []

        def al_terminar_pagina(p):
            p["campos"] = analizar_pagina(p["lineas"], p["ancho"], p["alto"])
            listas.append(p)
            # Publica avance parcial solo con las páginas procesadas sin recalcular análisis
            copia_paginas = [
                {"pagina": x["pagina"], "imagen": x["imagen"], "ancho": x["ancho"], "alto": x["alto"], "campos": dict(x["campos"])}
                for x in sorted(listas, key=lambda item: item["pagina"])
            ]
            personas_parcial, faltantes_parcial = _armar_personas(copia_paginas, gente)
            _poner_avance(trabajo, parcial={
                "campos": list(CAMPOS), "personas": personas_parcial,
                "faltantes": faltantes_parcial, "archivo": nombre_doc,
            })

        _poner_avance(trabajo, etapa="ocr", hecho=0, total=0,
                      mensaje="Preparando el lector...")
        paginas = ocr.leer_documentos(rutas_doc, os.path.join(carpeta, "img"),
                                      avisar, al_terminar_pagina)

        # Re-analizar limpiamente en orden secuencial 1..N
        paginas_finales = []
        for p in sorted(paginas, key=lambda x: x["pagina"]):
            c = analizar_pagina(p["lineas"], p["ancho"], p["alto"])
            paginas_finales.append({
                "pagina": p["pagina"],
                "imagen": p["imagen"],
                "ancho": p["ancho"],
                "alto": p["alto"],
                "campos": c
            })

        _poner_avance(trabajo, etapa="campos", hecho=len(paginas_finales),
                      total=len(paginas_finales), mensaje="Terminando...")
        personas, faltantes = _armar_personas(paginas_finales, gente)

        _guardar_resultado(trabajo, {
            "trabajo": trabajo,
            "creado": datetime.now().isoformat(timespec="seconds"),
            "archivo": nombre_doc,
            "listado": nombre_listado,
            "total_paginas": len(paginas),
            "personas": personas,
            "faltantes": faltantes,
            "campos": list(CAMPOS),
            "huella": huella,
        })
        _poner_avance(trabajo, etapa="listo", mensaje="Listo",
                      hecho=len(paginas), total=len(paginas))
    except Exception as e:                                  # noqa: BLE001
        traceback.print_exc()
        _poner_avance(trabajo, etapa="error", mensaje=str(e))


def _recibir():
    """Guarda lo subido y arranca la lectura. Devuelve el numero de trabajo."""
    documentos = [f for f in request.files.getlist("documento") if f and f.filename]
    if not documentos:
        raise ValueError("Elige el PDF o las imágenes con las cédulas.")

    archivo_listado = request.files.get("listado")
    tiene_listado = bool(archivo_listado and archivo_listado.filename)
    if tiene_listado and not mod_listado.es_archivo_aceptado(archivo_listado.filename):
        raise ValueError("El listado debe ser una hoja de cálculo (.xlsx, .xls, .csv).")

    huella = _huella(documentos + ([archivo_listado] if tiene_listado else []))

    trabajo = datetime.now().strftime("%Y%m%d-%H%M%S-") + uuid.uuid4().hex[:6]
    carpeta = _carpeta(trabajo)
    os.makedirs(os.path.join(carpeta, "img"), exist_ok=True)

    rutas = []
    for f in documentos:
        destino = os.path.join(carpeta, secure_filename(f.filename))
        f.save(destino)
        rutas.append(destino)

    ruta_listado = None
    nombre_listado = ""
    if tiene_listado:
        nombre_listado = archivo_listado.filename
        ruta_listado = os.path.join(carpeta, secure_filename(nombre_listado))
        archivo_listado.save(ruta_listado)

    nombre_doc = (documentos[0].filename if len(documentos) == 1
                  else f"{len(documentos)} imágenes")

    _poner_avance(trabajo, etapa="inicio", mensaje="Empezando...", hecho=0, total=0)
    threading.Thread(target=_procesar, daemon=True, args=(
        trabajo, rutas, ruta_listado, nombre_doc, nombre_listado, huella)).start()
    return trabajo


# ---------------------------------------------------------------------- rutas

def _peso(carpeta):
    """Cuanto ocupa en disco un trabajo. Casi todo son las imagenes de las paginas."""
    total = 0
    for raiz, _, archivos in os.walk(carpeta):
        for a in archivos:
            try:
                total += os.path.getsize(os.path.join(raiz, a))
            except OSError:
                pass
    return total


def _peso_legible(n):
    if n >= 1024 ** 3:
        return f"{n / 1024 ** 3:.1f} GB"
    if n >= 1024 ** 2:
        return f"{n / 1024 ** 2:.0f} MB"
    return f"{max(n, 1024) // 1024} KB"


def _fecha_legible(nombre, datos):
    """'12/08/2026 4:43 p. m.' a partir de la fecha guardada o del nombre."""
    crudo = (datos or {}).get("creado", "")
    momento = None
    try:
        momento = datetime.fromisoformat(crudo) if crudo else None
    except ValueError:
        momento = None
    if momento is None:
        # los trabajos que no terminaron no tienen fecha guardada, pero el nombre
        # de la carpeta es 20260812-044303-06a4a2
        try:
            momento = datetime.strptime(nombre[:15], "%Y%m%d-%H%M%S")
        except ValueError:
            return ""
    hora = momento.hour % 12 or 12
    ampm = "a. m." if momento.hour < 12 else "p. m."
    return f"{momento.day:02d}/{momento.month:02d}/{momento.year} {hora}:{momento.minute:02d} {ampm}"


def _trabajos_previos(limite=12):
    """El historial. Incluye los que no terminaron, para poder borrarlos."""
    salida = []
    if not os.path.isdir(config.DATOS):
        return salida
    for nombre in sorted(os.listdir(config.DATOS), reverse=True)[:limite]:
        carpeta = os.path.join(config.DATOS, nombre)
        if not os.path.isdir(carpeta):
            continue
        datos = _leer_resultado(nombre)
        salida.append({
            "id": nombre,
            "listo": datos is not None,
            "archivo": (datos or {}).get("archivo", "") or _archivo_suelto(carpeta),
            "listado": (datos or {}).get("listado", ""),
            "creado": _fecha_legible(nombre, datos),
            "personas": len((datos or {}).get("personas", [])),
            "paginas": (datos or {}).get("total_paginas", 0),
            "peso": _peso_legible(_peso(carpeta)),
        })
    return salida


def _archivo_suelto(carpeta):
    """Como se llamaba lo que se subio, cuando la lectura no llego a terminar."""
    for nombre in sorted(os.listdir(carpeta)):
        if ocr.es_archivo_aceptado(nombre):
            return nombre
    return "(sin nombre)"


@app.route("/")
def inicio():
    return jsonify({"status": "ok", "servicio": "SistemaOCR Python Engine", "version": "2.0"})


@app.route("/api/subir", methods=["POST"])
def subir():
    """Recibe el archivo y contesta en JSON, para que la pantalla no se recargue."""
    try:
        return jsonify({"trabajo": _recibir()})
    except ValueError as e:
        return jsonify({"error": str(e)}), 400


@app.route("/trabajo/<trabajo>")
def revisar(trabajo):
    if not os.path.isdir(_carpeta(trabajo)):
        abort(404)
    return render_template("app.html", trabajo=trabajo, trabajos=[])


@app.route("/api/<trabajo>/estado")
def estado(trabajo):
    if os.path.exists(_ruta_resultado(trabajo)):
        return jsonify({"etapa": "listo"})
    with _candado:
        e = dict(_avance.get(trabajo, {"etapa": "desconocido",
                                       "mensaje": "No encuentro ese trabajo."}))
    parcial = e.pop("parcial", None)          # va aparte: pesa y cambia poco
    e["cedulas"] = len(parcial["personas"]) if parcial else 0
    return jsonify(e)


@app.route("/api/<trabajo>/parcial")
def parcial(trabajo):
    """Lo leido hasta ahora, para ir mirando mientras el resto se procesa."""
    with _candado:
        e = _avance.get(trabajo) or {}
        p = e.get("parcial")
    if not p:
        abort(404)
    return jsonify(dict(p, parcial=True, total_paginas=e.get("total", 0),
                        listado="", trabajo=trabajo))


@app.route("/api/<trabajo>/datos")
def datos(trabajo):
    d = _leer_resultado(trabajo)
    if d is None:
        abort(404)
    return jsonify(d)


@app.route("/api/<trabajo>/guardar", methods=["POST"])
def guardar(trabajo):
    d = _leer_resultado(trabajo)
    if d is None:
        abort(404)
    cambios = request.get_json(silent=True) or {}
    por_id = {p["id"]: p for p in d["personas"]}

    for clave, valores in cambios.items():
        per = por_id.get(int(clave))
        if not per:
            continue
        for campo in CAMPOS:
            if campo in valores:
                nuevo = (valores[campo] or "").strip()
                if nuevo != per["valores"].get(campo, ""):
                    per["valores"][campo] = nuevo
                    per["editado"] = True
        for marca in ("descartada", "revisada"):
            if marca in valores:
                per[marca] = bool(valores[marca])
        # si corrigieron la fecha o el tipo, la edad y el aviso cambian con ellos
        recalcular_derivados(per)

    _guardar_resultado(trabajo, d)
    return jsonify({"ok": True, "guardado": datetime.now().strftime("%H:%M:%S")})


@app.route("/api/<trabajo>/borrar", methods=["POST"])
def borrar(trabajo):
    """Saca del historial una lectura y borra su carpeta con todo lo que guardo."""
    raiz = os.path.realpath(config.DATOS)
    entero = os.path.realpath(_carpeta(trabajo))
    # solo se borra lo que de verdad esta dentro de la carpeta de datos
    if not entero.startswith(raiz + os.sep) or not os.path.isdir(entero):
        return jsonify({"error": "No encuentro esa lectura en el historial."}), 404
    try:
        shutil.rmtree(entero)
    except OSError as e:
        return jsonify({"error": f"No pude borrarla: {e}"}), 500
    with _candado:
        _avance.pop(trabajo, None)
    return jsonify({"ok": True})


@app.route("/img/<trabajo>/<archivo>")
def imagen(trabajo, archivo):
    return send_from_directory(os.path.join(_carpeta(trabajo), "img"),
                               secure_filename(archivo))


def _armar_export(trabajo, formato):
    """Genera el archivo pedido. Devuelve (bytes, tipo, nombre sugerido)."""
    d = _leer_resultado(trabajo)
    if d is None:
        abort(404)
    personas = [p for p in d["personas"] if not p.get("descartada")]
    faltantes = d.get("faltantes", [])
    base = os.path.splitext(d.get("archivo") or "cedulas")[0][:40] or "cedulas"

    if formato == "csv":
        contenido, tipo = exportar.a_csv(personas, faltantes), "text/csv"
    elif formato == "xlsx":
        ficha_info = {
            "codigo_ficha": d.get("codigo_ficha") or "",
            "programa_formacion": d.get("programa_formacion") or d.get("listado") or ""
        }
        contenido = exportar.a_excel(personas, faltantes, ficha_info=ficha_info)
        tipo = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    elif formato == "html":
        import pagina_suelta
        contenido = pagina_suelta.construir(dict(d, personas=personas),
                                            os.path.join(_carpeta(trabajo), "img"))
        tipo = "text/html"
    else:
        abort(404)
    return contenido, tipo, f"{base} - datos.{formato}"


def _sin_pisar(carpeta, nombre):
    """Si ya existe un archivo asi, le agrega (2), (3)... en vez de pisarlo."""
    base, ext = os.path.splitext(nombre)
    ruta = os.path.join(carpeta, nombre)
    n = 2
    while os.path.exists(ruta):
        ruta = os.path.join(carpeta, f"{base} ({n}){ext}")
        n += 1
    return ruta


@app.route("/exportar/<trabajo>.<formato>")
def descargar(trabajo, formato):
    contenido, tipo, nombre = _armar_export(trabajo, formato)
    return send_file(io.BytesIO(contenido), mimetype=tipo, as_attachment=True,
                     download_name=nombre)


@app.route("/api/exportar-directo/<formato>", methods=["POST"])
def exportar_directo(formato):
    payload = request.get_json(silent=True) or {}
    personas = payload.get("personas", [])
    faltantes = payload.get("faltantes", [])
    ficha_info = {
        "codigo_ficha": payload.get("codigo_ficha", ""),
        "programa_formacion": payload.get("programa_formacion", "")
    }

    if formato == "xlsx":
        contenido = exportar.a_excel(personas, faltantes, ficha_info=ficha_info)
        tipo = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        nombre = f"Cruce_Cotejo_Ficha_{payload.get('codigo_ficha', 'Reporte')}.xlsx"
    elif formato == "csv":
        contenido = exportar.a_csv(personas, faltantes)
        tipo = "text/csv"
        nombre = f"Cruce_Cotejo_Ficha_{payload.get('codigo_ficha', 'Reporte')}.csv"
    else:
        abort(400)

    return send_file(io.BytesIO(contenido), mimetype=tipo, as_attachment=True, download_name=nombre)


@app.route("/api/<trabajo>/guardar-archivo", methods=["POST"])
def guardar_archivo(trabajo):
    """Escribe el archivo en Descargas y dice donde quedo.

    Dentro de la ventana propia no hay un navegador que gestione las descargas,
    asi que el programa las guarda el mismo y avisa la ruta exacta.
    """
    formato = (request.args.get("formato") or "").lower()
    contenido, _, nombre = _armar_export(trabajo, formato)

    carpeta = config.carpeta_descargas()
    try:
        os.makedirs(carpeta, exist_ok=True)
        ruta = _sin_pisar(carpeta, nombre)
        with open(ruta, "wb") as f:
            f.write(contenido)
    except OSError as e:
        return jsonify({"error": f"No pude guardar el archivo: {e}"}), 500

@app.route("/api/ping", methods=["GET"])
def ping():
    return jsonify({"ok": True, "servicio": "python_ocr"})


@app.route("/api/reiniciar", methods=["POST"])
def reiniciar():
    """Permite reiniciar limpiamente el proceso de Flask/Waitress."""
    def _shutdown():
        import time
        time.sleep(0.5)
        os._exit(0)
    threading.Thread(target=_shutdown, daemon=True).start()
    return jsonify({"ok": True, "mensaje": "Reiniciando microservicio..."})


# ------------------------------------------------------------------ arranque

def precalentar():
    """Carga los modelos del OCR apenas arranca.

    Sin esto, la primera lectura paga varios segundos extra cargando los modelos.
    Se hace en un hilo aparte para que la pantalla ya este disponible mientras.
    """
    try:
        import numpy as np
        ocr.motor()(np.full((80, 400, 3), 255, dtype=np.uint8))
        print("  Lector listo.")
    except Exception as e:                                  # noqa: BLE001
        print("  No pude precargar el lector:", e)


def preparar():
    """Deja todo listo para servir: carpetas y modelos."""
    os.makedirs(config.DATOS, exist_ok=True)
    threading.Thread(target=precalentar, daemon=True).start()


def servir():
    """Levanta el servidor con waitress o con el servidor nativo de Flask."""
    preparar()
    try:
        from waitress import serve
        serve(app, host=config.HOST, port=config.PUERTO, threads=8)
    except ImportError:
        app.run(host=config.HOST, port=config.PUERTO, threaded=True)


if __name__ == "__main__":
    print(f"\n  SistemaOCR Engine -> http://{config.HOST}:{config.PUERTO}\n")
    servir()
