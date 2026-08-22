/**
 * Dashboard de Cruce, Filtrado Interactivo, Modal Visor e Importación Final
 */
document.addEventListener('DOMContentLoaded', () => {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const tableRows = document.querySelectorAll('.cruce-row');
    const searchInput = document.getElementById('tableSearchInput');
    const btnImportarFinal = document.getElementById('btnImportarFinal');

    // Modal Visor
    const modalVisor = document.getElementById('modalVisor');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const modalDocImg = document.getElementById('modalDocImg');
    const modalTitle = document.getElementById('modalTitle');

    // Filtrado por Estado (Pestañas / Métricas)
    let currentFilter = 'ALL';

    function applyFilters() {
        const searchTerm = (searchInput?.value || '').toLowerCase().trim();

        tableRows.forEach(row => {
            const estado = row.getAttribute('data-estado');
            const rowText = row.innerText.toLowerCase();

            const matchesFilter = (currentFilter === 'ALL' || estado === currentFilter);
            const matchesSearch = (!searchTerm || rowText.includes(searchTerm));

            if (matchesFilter && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            filterButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentFilter = btn.getAttribute('data-filter') || 'ALL';
            applyFilters();
        });
    });

    searchInput?.addEventListener('input', applyFilters);

    // Modal para ver Cédula (Event Delegation para garantizar respuesta)
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-view-doc');
        if (!btn) return;

        const imgSrc = btn.getAttribute('data-img');
        const docNum = btn.getAttribute('data-doc') || 'No identificado';
        const docNombre = btn.getAttribute('data-nombre') || '';

        if (modalDocImg && modalTitle) {
            modalDocImg.src = imgSrc;
            modalTitle.innerText = `Documento: ${docNum} ${docNombre ? '- ' + docNombre : ''}`;
            modalVisor.style.display = 'flex';
        }
    });

    modalCloseBtn?.addEventListener('click', () => {
        if (modalVisor) modalVisor.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === modalVisor) {
            modalVisor.style.display = 'none';
        }
    });

    // Aprobación Manual de Discrepancia
    document.querySelectorAll('.btn-validar-manual').forEach(btn => {
        btn.addEventListener('click', async () => {
            const cruceId = btn.getAttribute('data-cruce-id');
            if (!confirm('¿Deseas aprobar este participante como válido?')) return;

            btn.disabled = true;
            try {
                const response = await window.SistemaOCR.fetchSecure(`${window.SistemaOCR.basePath}cruce/validar_manual`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ cruce_id: cruceId, accion: 'APROBAR' })
                });

                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + (data.error || 'No se pudo validar'));
                    btn.disabled = false;
                }
            } catch (err) {
                alert('Error en la solicitud de validación.');
                btn.disabled = false;
            }
        });
    });

    // Importación Final a Base de Datos
    btnImportarFinal?.addEventListener('click', async () => {
        const fichaId = btnImportarFinal.getAttribute('data-ficha-id');
        if (!confirm('¿Estás seguro de importar los registros conciliados y aprobados a la base de datos oficial?')) {
            return;
        }

        btnImportarFinal.disabled = true;
        btnImportarFinal.innerText = 'Importando...';

        try {
            const response = await window.SistemaOCR.fetchSecure(`${window.SistemaOCR.basePath}index.php?ruta=cruce/importar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ ficha_id: fichaId })
            });

            const data = await response.json();
            if (data.success) {
                alert(data.mensaje);
                window.location.href = `${window.SistemaOCR.basePath}home/index`;
            } else {
                alert('Error al importar: ' + (data.error || 'No se pudo completar la importación.'));
                btnImportarFinal.disabled = false;
                btnImportarFinal.innerText = 'Importar Aprobados a BD';
            }
        } catch (err) {
            alert('Error de conexión al importar.');
            btnImportarFinal.disabled = false;
            btnImportarFinal.innerText = 'Importar Aprobados a BD';
        }
    });

    // Eliminar y Reprocesar Ficha (Event Delegation por si el DOM no había enlazado el ID)
    document.addEventListener('click', async (e) => {
        const target = e.target.closest('#btnEliminarReprocesar');
        if (!target) return;

        e.preventDefault();
        const fichaId = target.getAttribute('data-ficha-id');
        const codigoFicha = target.getAttribute('data-codigo');

        if (!confirm(`¿Estás seguro de eliminar la Ficha N° ${codigoFicha}? Se borrarán los datos extraídos y podrás volver a subir los archivos.`)) {
            return;
        }

        target.disabled = true;
        target.innerText = 'Eliminando...';

        try {
            const response = await window.SistemaOCR.fetchSecure(`${window.SistemaOCR.basePath}index.php?ruta=ficha/eliminar`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ ficha_id: fichaId })
            });

            const data = await response.json();
            if (data.success) {
                alert('Ficha eliminada con éxito. Redirigiendo al formulario de carga...');
                window.location.href = `${window.SistemaOCR.basePath}index.php?ruta=ficha/subir`;
            } else {
                alert('Error al eliminar: ' + (data.error || 'No se pudo eliminar la ficha.'));
                target.disabled = false;
                target.innerText = 'Borrar Ficha y Reprocesar';
            }
        } catch (err) {
            alert('Error de conexión al eliminar la ficha: ' + err.message);
            target.disabled = false;
            target.innerText = 'Borrar Ficha y Reprocesar';
        }
    });
});
