"""Abre el lector como una aplicacion de escritorio, con su propia ventana.

Por dentro sigue siendo la misma aplicacion web, pero servida solo a este equipo
y mostrada en una ventana sin barra de direcciones: no hay que abrir el navegador
ni escribir ninguna direccion.

Si la ventana no se puede crear (falta WebView2, por ejemplo), cae de vuelta al
navegador para no dejar al usuario sin nada.
"""
import socket
import sys
import threading
import time

import config
from .servidor import app, preparar


def _puerto_libre(inicial):
    """Busca un puerto disponible por si el de siempre esta ocupado."""
    for puerto in range(inicial, inicial + 20):
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex((config.HOST, puerto)) != 0:
                return puerto
    raise RuntimeError("No encontre ningun puerto libre.")


def _servir_en_hilo(puerto):
    from waitress import serve
    hilo = threading.Thread(
        target=lambda: serve(app, host=config.HOST, port=puerto, threads=8),
        daemon=True)
    hilo.start()
    return hilo


def _esperar(puerto, segundos=15):
    """Espera a que el servidor conteste antes de abrir la ventana."""
    limite = time.time() + segundos
    while time.time() < limite:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            if s.connect_ex((config.HOST, puerto)) == 0:
                return True
        time.sleep(0.1)
    return False


def _avisar_del_fallo(detalle):
    """Deja el error en un archivo y lo muestra en pantalla.

    Al abrirse sin consola (doble clic), un error suelto haria que la ventana
    simplemente no apareciera y no se sabria por que.
    """
    import os
    ruta = os.path.join(config.RAIZ, "error.log")
    try:
        with open(ruta, "w", encoding="utf-8") as f:
            f.write(detalle)
    except OSError:
        pass
    try:
        import ctypes
        ctypes.windll.user32.MessageBoxW(
            None,
            f"No pude abrir el lector de cédulas.\n\n{detalle.strip().splitlines()[-1]}"
            f"\n\nEl detalle quedó en:\n{ruta}",
            config.TITULO, 0x10)
    except Exception:                                       # noqa: BLE001
        print(detalle)


def main():
    # la consola de Windows no siempre viene en UTF-8 y los acentos salen rotos
    for flujo in (sys.stdout, sys.stderr):
        try:
            flujo.reconfigure(encoding="utf-8", errors="replace")
        except (AttributeError, ValueError):
            pass

    preparar()
    puerto = _puerto_libre(config.PUERTO)
    direccion = f"http://{config.HOST}:{puerto}"

    print(f"\n  {config.TITULO}")
    print("  Abriendo la ventana... (esta consola puede quedarse minimizada)\n")

    _servir_en_hilo(puerto)
    if not _esperar(puerto):
        print("  El servidor no arrancó a tiempo.")
        return 1

    try:
        import webview
        webview.create_window(config.TITULO, direccion,
                              width=1380, height=880, min_size=(900, 600),
                              text_select=True)
        webview.start()
        return 0
    except Exception as e:                                  # noqa: BLE001
        # Sin ventana propia: al menos que lo abra el navegador.
        print(f"  No pude abrir la ventana ({e}).")
        print(f"  Lo abro en el navegador: {direccion}")
        import webbrowser
        webbrowser.open(direccion)
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            return 0


if __name__ == "__main__":
    import traceback
    try:
        sys.exit(main())
    except SystemExit:
        raise
    except Exception:                                       # noqa: BLE001
        _avisar_del_fallo(traceback.format_exc())
        sys.exit(1)
