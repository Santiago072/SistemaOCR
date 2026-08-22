/**
 * Dashboard de Cruce, Filtrado Interactivo, Modal Visor e Importación Final para Laravel
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

    // Modal para ver Cédula
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

    // Importación Final a Base de Datos
    btnImportarFinal?.addEventListener('click', async () => {
        const url = btnImportarFinal.getAttribute('data-url');
        const result = await Swal.fire({
            title: '¿Confirmar importación?',
            text: 'Se guardarán todos los registros conciliados y aprobados en la tabla oficial de participantes finales.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, importar ahora',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        btnImportarFinal.disabled = true;
        btnImportarFinal.innerText = 'Importando...';

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                }
            });

            const data = await response.json();
            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: '¡Importación Exitosa!',
                    text: data.mensaje
                });
                window.location.href = '/';
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'No se pudo completar la importación.'
                });
                btnImportarFinal.disabled = false;
                btnImportarFinal.innerText = 'Importar Aprobados a BD';
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo comunicar con el servidor.'
            });
            btnImportarFinal.disabled = false;
            btnImportarFinal.innerText = 'Importar Aprobados a BD';
        }
    });

    // Eliminar y Reprocesar Ficha
    document.addEventListener('click', async (e) => {
        const target = e.target.closest('#btnEliminarReprocesar');
        if (!target) return;

        e.preventDefault();
        const url = target.getAttribute('data-url');
        const codigo = target.getAttribute('data-codigo');

        const result = await Swal.fire({
            title: '¿Borrar Ficha y Reprocesar?',
            text: `Se eliminarán los registros de la Ficha ${codigo} para permitir una carga limpia desde cero.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sí, borrar y reiniciar',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        target.disabled = true;
        target.innerText = 'Borrando...';

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token
                }
            });

            const data = await response.json();
            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Ficha Eliminada',
                    text: data.mensaje
                });
                window.location.href = '/';
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'No se pudo eliminar la ficha.'
                });
                target.disabled = false;
                target.innerText = 'Borrar Ficha y Reprocesar';
            }
        } catch (err) {
            Swal.fire({
                icon: 'error',
                title: 'Error de conexión',
                text: 'No se pudo comunicar con el servidor.'
            });
            target.disabled = false;
            target.innerText = 'Borrar Ficha y Reprocesar';
        }
    });
});
