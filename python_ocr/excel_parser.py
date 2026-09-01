"""
Parser de Archivos Excel (.xlsx / .xls / .csv) de Reportes de Inscripción SENA
Estructura procesada según la plantilla de inscripción:
- Fila 3: Código Ficha (ej: 3574135 o 3590737)
- Fila 4: Programa de Formación
- Fila 6+: Columnas -> Identificación (ej: CC - 1110487315 o TI - 1117932285), Nombre, Estado
"""

import os
import sys
import json
import argparse
import re

# Asegurar que el directorio de python_ocr esté en sys.path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

from listado import cargar

def parse_excel_file(file_path: str) -> dict:
    if not os.path.exists(file_path):
        return {
            "status": "error",
            "message": f"Archivo Excel no encontrado: {file_path}",
            "data": None
        }

    try:
        from listado import _hojas
        hojas = _hojas(file_path)
        programa_formacion = "PROGRAMA DE FORMACION SENA"
        codigo_ficha = "SIN_CODIGO"

        for _, filas in hojas:
            for fila in filas[:15]:
                fila_str = [str(c).strip() for c in fila if c is not None]
                for i, celda in enumerate(fila_str):
                    c_low = celda.lower()
                    if any(k in c_low for k in ("programa", "curso", "carrera", "formaci", "especialidad")) and i + 1 < len(fila_str) and fila_str[i+1]:
                        programa_formacion = fila_str[i+1]
                    elif any(k in c_low for k in ("código ficha", "codigo ficha", "ficha", "grupo", "id curso", "codigo curso")):
                        if i + 1 < len(fila_str) and fila_str[i+1]:
                            codigo_ficha = fila_str[i+1]

        personas = cargar(file_path)

        # Si no se extrajo la ficha de las celdas, extraer del nombre del archivo
        if codigo_ficha == "SIN_CODIGO":
            nombre_base = os.path.basename(file_path)
            fichas_encontradas = re.findall(r'(\d{6,8})', nombre_base)
            if fichas_encontradas:
                codigo_ficha = fichas_encontradas[-1]
            else:
                codigo_ficha = "SIN_CODIGO"

        aspirantes = []
        for p in personas:
            doc = p.get("documento", "")
            tipo = p.get("tipo", "CC")
            nombre_completo = p.get("nombre_completo", "")
            
            if not tipo:
                tipo = "CC"

            # Detectar estado de inscripción en cualquier columna extra disponible
            estado_val = "Inscrito"
            for k_ext, v_ext in p.get("extra", {}).items():
                if any(x in k_ext.lower() for x in ("estado", "condicion", "status", "resultado")):
                    estado_val = str(v_ext).strip()
                    break

            aspirantes.append({
                "tipo_documento": tipo,
                "numero_documento": doc,
                "nombre_completo": nombre_completo,
                "nombres": p.get("nombres", ""),
                "apellidos": p.get("apellidos", ""),
                "estado_inscripcion": estado_val
            })

        return {
            "status": "success",
            "data": {
                "codigo_ficha": codigo_ficha,
                "programa_formacion": programa_formacion,
                "aspirantes": aspirantes
            }
        }
    except Exception as e:
        return {
            "status": "error",
            "message": f"Error al parsear Excel: {str(e)}",
            "data": None
        }

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Parser de reportes de inscripción Excel SENA")
    parser.add_argument("--excel", required=True, help="Ruta al archivo Excel")
    args = parser.parse_args()

    result = parse_excel_file(args.excel)
    print(json.dumps(result, ensure_ascii=False))
