FROM python:3.11-slim

# Instalar dependencias del sistema requeridas para OpenCV y procesamiento de imágenes
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 \
    libglib2.0-0 \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copiar requirements e instalar dependencias de Python
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copiar el código del servicio
COPY . .

# Puerto por defecto de FastAPI
EXPOSE 8000

# Ejecutar con Uvicorn en producción
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "2"]
