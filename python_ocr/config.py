"""Rutas y ajustes del proyecto, en un solo lugar."""
import os

# Configuración nativa para SistemaOCR
RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

WEB = os.path.join(RAIZ, "public")
PLANTILLAS = os.path.join(RAIZ, "app", "views")
ESTATICOS = os.path.join(RAIZ, "public")
DATOS = os.path.join(RAIZ, "uploads", "tmp")

def carpeta_descargas():
    """Donde se guardan los archivos que se exportan.

    Se usa la carpeta Descargas del usuario, que es donde uno los busca. Si el
    sistema la tiene en otro sitio (OneDrive, por ejemplo), se lee del registro.
    """
    try:
        import winreg
        clave = r"Software\Microsoft\Windows\CurrentVersion\Explorer\Shell Folders"
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, clave) as k:
            ruta = winreg.QueryValueEx(k, "{374DE290-123F-4565-9164-39C4925E467B}")[0]
            if ruta and os.path.isdir(ruta):
                return ruta
    except OSError:
        pass
    por_defecto = os.path.join(os.path.expanduser("~"), "Downloads")
    return por_defecto if os.path.isdir(por_defecto) else os.path.expanduser("~")


HOST = "127.0.0.1"        # solo este equipo: nada queda expuesto en la red
PUERTO = 5005
TAMANO_MAXIMO = 200 * 1024 * 1024      # 200 MB por archivo subido

# Titulo de la aplicación
TITULO = "SistemaOCR Engine"
