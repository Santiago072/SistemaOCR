/**
 * Renderizado de Tabla General de Cotejo en Streaming y Tiempo Real
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.cruce-container');
    const trabajoId = container?.getAttribute('data-trabajo');
    const fichaId = container?.getAttribute('data-ficha-id');
    const basePath = window.SistemaOCR?.basePath || '/SistemaOCR/';

    // Elementos de Progreso
    const barraLeyendo = document.getElementById('barraLeyendoStream');
    const leyendoTexto = document.getElementById('leyendoStreamTexto');
    const leyendoFill = document.getElementById('leyendoStreamFill');
    const cronometroStream = document.getElementById('cronometroStream');
    const tiempoLecturaBox = document.getElementById('tiempoLecturaBox');

    // Métricas
    const countCorrectas = document.getElementById('countCorrectas');
    const countErrores = document.getElementById('countErrores');
    const countSoloPdf = document.getElementById('countSoloPdf');
    const countSoloExcel = document.getElementById('countSoloExcel');

    // Tabla y Búsqueda
    const tablaBody = document.getElementById('tablaResultadosBody');
    const inputBuscar = document.getElementById('inputBuscarTabla');

    // Modal
    const modalVisor = document.getElementById('modalVisorCedula');
    const modalTitulo = document.getElementById('modalTituloCedula');
    const modalImagen = document.getElementById('modalImagenDoc');
    const btnCerrarModal = document.getElementById('btnCerrarModal');

    let datosGlobales = null;
    let segundosTranscurridos = 0;
    let intervaloCronometro = null;
    let filtroActivo = 'todos';

    if (!trabajoId && window.DATOS_INICIALES_INFORME && window.DATOS_INICIALES_INFORME.personas?.length) {
        renderizarTablaGlobal(window.DATOS_INICIALES_INFORME);
        if (barraLeyendo) barraLeyendo.hidden = true;
    }

    if (trabajoId) {
        iniciarCronometro();
        monitorearProgreso();
    } else {
        cargarDatosDesdeBD();
    }

    function iniciarCronometro() {
        segundosTranscurridos = 0;
        intervaloCronometro = setInterval(() => {
            segundosTranscurridos++;
            const mins = Math.floor(segundosTranscurridos / 60);
            const secs = segundosTranscurridos % 60;
            const tiempoStr = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
            if (cronometroStream) cronometroStream.innerText = `⏱️ ${tiempoStr}`;
        }, 1000);
    }

    function detenerCronometro() {
        if (intervaloCronometro) {
            clearInterval(intervaloCronometro);
            intervaloCronometro = null;
        }
        const mins = Math.floor(segundosTranscurridos / 60);
        const secs = segundosTranscurridos % 60;
        const tiempoFinal = mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
        if (tiempoLecturaBox) {
            tiempoLecturaBox.innerHTML = `<strong>Tiempo de lectura:</strong> ${tiempoFinal}`;
        }
    }

    function cargarDatosDesdeBD() {
        if (barraLeyendo) barraLeyendo.hidden = true;
        fetch(`${basePath}index.php?ruta=api/datosTrabajo&ficha_id=${encodeURIComponent(fichaId)}`)
            .then(res => res.ok ? res.json() : null)
            .then(datos => {
                if (datos && (datos.personas || datos.faltantes)) {
                    renderizarTablaGlobal(datos);
                }
            })
            .catch(err => console.error('Error cargando informe guardado:', err));
    }

    function monitorearProgreso() {
        fetch(`${basePath}index.php?ruta=api/estadoTrabajo&trabajo=${encodeURIComponent(trabajoId)}`)
            .then(res => res.json())
            .then(estado => {
                if (estado.etapa === 'listo') {
                    if (barraLeyendo) barraLeyendo.hidden = true;
                    detenerCronometro();
                    cargarDatosFinales();
                    return;
                }

                if (estado.etapa === 'error') {
                    detenerCronometro();
                    if (leyendoTexto) leyendoTexto.innerText = 'Error: ' + (estado.mensaje || 'Fallo de lectura');
                    return;
                }

                if (leyendoTexto) leyendoTexto.innerText = estado.mensaje || `Leyendo documento (${estado.hecho || 0}/${estado.total || 0})...`;
                if (leyendoFill && estado.total > 0) {
                    const pct = Math.round(((estado.hecho || 0) / estado.total) * 100);
                    leyendoFill.style.width = pct + '%';
                }

                if (estado.cedulas > 0) {
                    cargarAvanceParcial();
                }

                setTimeout(monitorearProgreso, estado.cedulas > 0 ? 1000 : 500);
            })
            .catch(() => {
                setTimeout(monitorearProgreso, 2000);
            });
    }

    function cargarAvanceParcial() {
        fetch(`${basePath}index.php?ruta=api/parcialTrabajo&trabajo=${encodeURIComponent(trabajoId)}`)
            .then(res => res.ok ? res.json() : null)
            .then(datos => {
                if (datos && datos.personas) {
                    renderizarTablaGlobal(datos);
                }
            });
    }

    async function cargarDatosFinales() {
        try {
            const res = await fetch(`${basePath}index.php?ruta=api/datosTrabajo&trabajo=${encodeURIComponent(trabajoId)}`);
            if (!res.ok) return;
            const datos = await res.json();
            if (datos) {
                renderizarTablaGlobal(datos);
                await autoSincronizarEnBD();
                try {
                    const urlLimpia = `${basePath}index.php?ruta=cruce/informe&ficha=${encodeURIComponent(fichaId)}`;
                    window.history.replaceState({}, document.title, urlLimpia);
                    const btnExp = document.getElementById('btnExportarExcel');
                    if (btnExp) {
                        btnExp.href = `${basePath}index.php?ruta=cruce/exportarExcel&ficha=${encodeURIComponent(fichaId)}`;
                    }
                } catch (e) {
                    // ignore
                }
            }
        } catch (e) {
            console.error('Error al cargar datos finales:', e);
        }
    }

    function renderizarTablaGlobal(datos) {
        datosGlobales = datos;
        const personas = datos.personas || [];
        const faltantes = datos.faltantes || [];

        // 1. Actualizar Métricas
        let ok = 0, err = 0, soloPdf = 0;
        personas.forEach(p => {
            if (p.estado === 'ok') ok++;
            else if (p.estado === 'revisar') err++;
            else if (p.estado === 'sin_listado') soloPdf++;
        });

        if (countCorrectas) countCorrectas.innerText = ok;
        if (countErrores) countErrores.innerText = err;
        if (countSoloPdf) countSoloPdf.innerText = soloPdf;
        if (countSoloExcel) countSoloExcel.innerText = faltantes.length;

        // 2. Renderizar Filas de la Tabla
        if (!tablaBody) return;
        const search = (inputBuscar?.value || '').toLowerCase().trim();
        tablaBody.innerHTML = '';

        // Si el filtro activo es 'faltantes' (Solo en Excel), renderizar los aspirantes no encontrados en el PDF
        if (filtroActivo === 'faltantes') {
            faltantes.forEach((f, idx) => {
                const doc = f.documento || '-';
                const nom = f.nombre_completo || `${f.nombres || ''} ${f.apellidos || ''}`.trim() || 'Sin Nombre';
                const tipo = f.tipo || 'CC';

                if (search && !nom.toLowerCase().includes(search) && !doc.includes(search)) {
                    return;
                }

                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid #e2e8f0';
                tr.style.background = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

                tr.innerHTML = `
                    <td style="padding: 10px 12px; text-align: center; font-weight: bold; color: #94a3b8;">-</td>
                    <td style="padding: 10px 12px; text-align: center; font-weight: bold; color: #64748b;">${escapeHtml(tipo)}</td>
                    <td style="padding: 10px 12px; font-weight: 600; color: #1e293b;">${escapeHtml(doc)}</td>
                    <td style="padding: 10px 12px; font-weight: bold; color: #475569;">${escapeHtml(nom)}</td>
                    <td style="padding: 10px 12px; text-align: center;">
                        <span class="status-badge" style="background:#f1f5f9; color:#475569; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;">Solo Excel (Sin cédula)</span>
                    </td>
                    <td style="padding: 10px 12px; text-align: center; color: #94a3b8; font-size: 0.8rem;">-</td>
                `;
                tablaBody.appendChild(tr);
            });
            return;
        }

        personas.forEach((p, idx) => {
            const vals = p.valores || {};
            const listado = p.listado || {};
            const pag = (p.paginas && p.paginas[0]?.pagina) || (idx + 1);
            const imgName = (p.paginas && p.paginas[0]?.imagen) || '';

            const nombreCompleto = `${vals.nombres || ''} ${vals.apellidos || ''}`.trim() || 'Sin Nombre';
            const doc = vals.documento || '-';
            const tipo = vals.tipo_documento || 'CC';
            const estado = p.estado || 'ok';
            const novedad = p.novedad || p.estado_texto || '';

            if (search && !nombreCompleto.toLowerCase().includes(search) && !doc.includes(search)) {
                return;
            }

            if (filtroActivo !== 'todos' && estado !== filtroActivo) {
                return;
            }

            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid #e2e8f0';
            tr.style.background = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

            const tieneRevisarNombre = Boolean(
                (p.campos?.apellidos?.revisar || p.campos?.nombres?.revisar || p.comparacion?.apellidos?.revisar || p.comparacion?.nombres?.revisar) &&
                (!p.referencia && estado !== 'ok')
            );

            let badgeHtml = '<span class="status-badge" style="background:#dcfce7; color:#15803d; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;">Correcto</span>';
            if (tieneRevisarNombre) {
                badgeHtml = '<span class="status-badge" style="background:#fef3c7; color:#b45309; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;" title="Nombre o apellido sin espacio confirmado">⚠️ Revisar nombre</span>';
            } else if (estado === 'revisar') {
                const motivo = (novedad && !novedad.toLowerCase().includes('conciliado') && !novedad.toLowerCase().includes('verificado')) ? `: ${novedad}` : '';
                badgeHtml = `<span class="status-badge" style="background:#fef9c3; color:#854d0e; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;" title="${escapeHtml(novedad)}">Diferencia${escapeHtml(motivo)}</span>`;
            } else if (estado === 'sin_listado') {
                badgeHtml = '<span class="status-badge" style="background:#fee2e2; color:#b91c1c; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;">Solo PDF</span>';
            }

            tr.innerHTML = `
                <td style="padding: 10px 12px; text-align: center; font-weight: bold; color: #64748b;">${pag}</td>
                <td style="padding: 10px 12px; text-align: center; font-weight: bold; color: ${tipo === 'TI' ? '#4f46e5' : '#0f172a'};">${escapeHtml(tipo)}</td>
                <td style="padding: 10px 12px; font-weight: 600; color: #1e293b;">${escapeHtml(doc)}</td>
                <td style="padding: 10px 12px; font-weight: bold; color: ${estado === 'revisar' ? '#b45309' : '#0f172a'};">${escapeHtml(nombreCompleto)}</td>
                <td style="padding: 10px 12px; text-align: center;">${badgeHtml}</td>
                <td style="padding: 10px 12px; text-align: center;">
                    ${imgName ? `<button type="button" class="btn-sm btn-outline btn-ver-doc" data-idx="${idx}" data-img="${escapeHtml(imgName)}" data-nombre="${escapeHtml(nombreCompleto)}" data-tipo="${escapeHtml(tipo)}" data-doc="${escapeHtml(doc)}" data-nombres="${escapeHtml(vals.nombres || '')}" data-apellidos="${escapeHtml(vals.apellidos || '')}" style="padding: 4px 10px; font-size: 0.78rem; cursor: pointer;">🔍 Ver Cédula</button>` : '-'}
                </td>
            `;

            tablaBody.appendChild(tr);
        });

        // Eventos en botones de "Ver Cédula"
        document.querySelectorAll('.btn-ver-doc').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = btn.getAttribute('data-idx');
                const img = btn.getAttribute('data-img');
                const nom = btn.getAttribute('data-nombre');
                const tipo = btn.getAttribute('data-tipo');
                const doc = btn.getAttribute('data-doc');
                const nombres = btn.getAttribute('data-nombres');
                const apellidos = btn.getAttribute('data-apellidos');
                abrirModalVisor(img, nom, idx, tipo, doc, nombres, apellidos);
            });
        });
    }

    function abrirModalVisor(imgFile, nombre, idx = '', tipo = 'CC', doc = '', nombres = '', apellidos = '') {
        if (!modalVisor || !modalImagen) return;
        let imgUrl = '';
        if (imgFile.startsWith('uploads/') || imgFile.startsWith('/uploads/') || imgFile.includes('/')) {
            imgUrl = `${basePath}${imgFile.replace(/^\/+/, '')}`;
        } else {
            imgUrl = `http://127.0.0.1:5005/img/${encodeURIComponent(trabajoId || '')}/${encodeURIComponent(imgFile)}`;
        }
        modalImagen.src = imgUrl;
        if (modalTitulo) modalTitulo.innerText = `Cédula: ${nombre || 'Sin Nombre'}`;

        const inputIdx = document.getElementById('modalPersonaIdx');
        const selectTipo = document.getElementById('modalEditTipo');
        const inputDoc = document.getElementById('modalEditDoc');
        const inputNombres = document.getElementById('modalEditNombres');
        const inputApellidos = document.getElementById('modalEditApellidos');

        if (inputIdx) inputIdx.value = idx;
        if (selectTipo) selectTipo.value = (tipo === '-' || !tipo) ? 'CC' : tipo;
        if (inputDoc) inputDoc.value = doc === '-' ? '' : doc;
        if (inputNombres) inputNombres.value = nombres;
        if (inputApellidos) inputApellidos.value = apellidos;

        modalVisor.style.display = 'flex';
    }

    // Guardar corrección manual desde el modal
    document.getElementById('btnGuardarEdicionModal')?.addEventListener('click', async () => {
        const idxStr = document.getElementById('modalPersonaIdx')?.value;
        if (idxStr === '' || idxStr === undefined) return;
        const idx = parseInt(idxStr, 10);
        if (isNaN(idx) || !datosGlobales || !datosGlobales.personas || !datosGlobales.personas[idx]) return;

        const nuevoTipo = document.getElementById('modalEditTipo')?.value || 'CC';
        const nuevoDoc = document.getElementById('modalEditDoc')?.value.trim() || '';
        const nuevosNombres = document.getElementById('modalEditNombres')?.value.trim() || '';
        const nuevosApellidos = document.getElementById('modalEditApellidos')?.value.trim() || '';

        const persona = datosGlobales.personas[idx];
        if (!persona.valores) persona.valores = {};
        persona.valores.tipo_documento = nuevoTipo;
        persona.valores.documento = nuevoDoc;
        persona.valores.nombres = nuevosNombres;
        persona.valores.apellidos = nuevosApellidos;
        persona.editado = true;

        // Limpiar marcas de revisión previa ya que el usuario lo corrigió a mano
        if (persona.campos?.nombres) {
            persona.campos.nombres.revisar = false;
            persona.campos.nombres.valor = nuevosNombres;
        }
        if (persona.campos?.apellidos) {
            persona.campos.apellidos.revisar = false;
            persona.campos.apellidos.valor = nuevosApellidos;
        }

        // Evaluar discrepancias y coincidencias con la lista de referencia/faltantes
        const refDoc = persona.referencia?.documento || persona.listado?.documento || '';
        const refNom = persona.referencia?.nombre_completo || persona.listado?.nombre_completo || '';
        const refTipo = persona.referencia?.tipo || persona.listado?.tipo_documento || 'CC';

        if (refDoc) {
            // Ya tenía una referencia asignada: comprobar si los nuevos datos siguen coincidiendo
            const docCoincide = (!refDoc || !nuevoDoc || refDoc === nuevoDoc);
            const tipoCoincide = (!refTipo || !nuevoTipo || refTipo.toUpperCase() === nuevoTipo.toUpperCase());
            
            // Evaluar coincidencia de nombres y apellidos contra el nombre esperado en Excel
            const limNorm = (str) => (str || '').toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9]/g, "");
            const ingresado = limNorm(`${nuevosNombres} ${nuevosApellidos}`);
            const esperado = limNorm(refNom);
            
            // Si el usuario modificó/borró/pegó letras en nombres o apellidos y ya no coincide exactamente con el Excel
            const nombreCoincide = (ingresado === esperado);

            if (!docCoincide) {
                persona.estado = 'revisar';
                persona.novedad = 'Difiere en número';
            } else if (!tipoCoincide) {
                persona.estado = 'revisar';
                persona.novedad = 'Difiere en tipo de documento';
            } else if (!nombreCoincide) {
                persona.estado = 'revisar';
                persona.novedad = 'Difiere en nombres o apellidos';
            } else {
                persona.estado = 'ok';
                persona.novedad = 'Corregido manualmente (Coincide)';
            }
        } else if (datosGlobales.faltantes && datosGlobales.faltantes.length > 0) {
            // No tenía referencia (ej: Solo PDF): buscar si el nuevo número coincide con algún faltante de Excel
            const faltanteIdx = datosGlobales.faltantes.findIndex(f => 
                (nuevoDoc && f.documento === nuevoDoc) ||
                (nuevosNombres && f.nombre_completo && f.nombre_completo.toLowerCase().includes(nuevosNombres.toLowerCase()))
            );
            if (faltanteIdx !== -1) {
                const faltante = datosGlobales.faltantes.splice(faltanteIdx, 1)[0];
                persona.referencia = faltante;
                persona.listado = {
                    documento: faltante.documento,
                    nombre_completo: faltante.nombre_completo,
                    tipo_documento: faltante.tipo || 'CC'
                };
                persona.estado = 'ok';
                persona.novedad = 'Corregido manualmente (Vinculado con Excel)';
            } else {
                persona.estado = 'sin_listado';
                persona.novedad = 'No aparece en el listado';
            }
        }

        renderizarTablaGlobal(datosGlobales);

        // Notificar al backend de Python si hay un trabajo activo
        if (trabajoId) {
            try {
                const perId = persona.id !== undefined ? persona.id : idx;
                const payload = {};
                payload[perId] = {
                    tipo_documento: nuevoTipo,
                    documento: nuevoDoc,
                    nombres: nuevosNombres,
                    apellidos: nuevosApellidos,
                    revisada: true
                };
                await fetch(`http://127.0.0.1:5005/api/${encodeURIComponent(trabajoId)}/guardar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
            } catch (e) {
                console.warn('No se pudo sincronizar edición con python_ocr:', e);
            }
        }

        if (modalVisor) modalVisor.style.display = 'none';
    });

    btnCerrarModal?.addEventListener('click', () => {
        if (modalVisor) modalVisor.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
        if (e.target === modalVisor) modalVisor.style.display = 'none';
    });

    inputBuscar?.addEventListener('input', () => {
        if (datosGlobales) renderizarTablaGlobal(datosGlobales);
    });

    // Filtros de métricas por clic en las tarjetas (permite activar o alternar)
    document.querySelectorAll('.card-filtro-btn').forEach(btn => {
        btn.style.cursor = 'pointer';
        btn.addEventListener('click', () => {
            const filterValue = btn.getAttribute('data-filter') || 'todos';
            const wasActive = btn.classList.contains('active');

            document.querySelectorAll('.card-filtro-btn').forEach(b => b.classList.remove('active'));

            if (wasActive) {
                filtroActivo = 'todos';
            } else {
                btn.classList.add('active');
                filtroActivo = filterValue;
            }

            if (datosGlobales) {
                renderizarTablaGlobal(datosGlobales);
            }
        });
    });

    async function autoSincronizarEnBD() {
        const currentFichaId = fichaId || container?.getAttribute('data-ficha-id');
        const currentTrabajoId = trabajoId || container?.getAttribute('data-trabajo');
        if (!currentFichaId || !currentTrabajoId) return;
        try {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
            const formData = new FormData();
            formData.append('ficha_id', currentFichaId);
            formData.append('trabajo_id', currentTrabajoId);
            formData.append('tiempo_seg', segundosTranscurridos);
            formData.append('csrf_token', csrfToken);

            const res = await fetch(`${basePath}index.php?ruta=cruce/sincronizar`, {
                method: 'POST',
                headers: { 
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });
            const resJson = await res.json();
            console.log('Resultado auto-sincronización:', resJson);
        } catch (e) {
            console.warn('Error al auto-sincronizar:', e);
        }
    }

    // Botones de Guardar en BD y Borrar
    const btnImportarFinal = document.getElementById('btnImportarFinal');
    btnImportarFinal?.addEventListener('click', async () => {
        const currentFichaId = fichaId || container?.getAttribute('data-ficha-id');
        const currentTrabajoId = trabajoId || container?.getAttribute('data-trabajo');
        if (!currentFichaId) {
            alert('No se detectó el identificador de la ficha.');
            return;
        }

        btnImportarFinal.disabled = true;
        btnImportarFinal.innerText = 'Guardando en BD...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            if (currentTrabajoId) {
                const params = new URLSearchParams({ ficha_id: currentFichaId, trabajo_id: currentTrabajoId, csrf_token: csrfToken });
                await fetch(`${basePath}index.php?ruta=cruce/sincronizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': csrfToken },
                    body: params
                });
            }
            alert('Los datos del informe se han guardado exitosamente en la base de datos.');
        } catch (e) {
            alert('Error al guardar en la base de datos: ' + e.message);
        } finally {
            btnImportarFinal.disabled = false;
            btnImportarFinal.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg> Guardar / Sincronizar en BD`;
        }
    });

    const btnEliminarReprocesar = document.getElementById('btnEliminarReprocesar');
    btnEliminarReprocesar?.addEventListener('click', async () => {
        const cod = btnEliminarReprocesar.getAttribute('data-codigo');
        if (!confirm(`¿Estás seguro de borrar los datos de la ficha ${cod} y volver a subir?`)) return;

        btnEliminarReprocesar.disabled = true;
        btnEliminarReprocesar.innerText = 'Borrando...';

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const params = new URLSearchParams({ ficha_id: fichaId, csrf_token: csrfToken });
            const response = await fetch(`${basePath}index.php?ruta=ficha/eliminar`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: params
            });

            const data = await response.json();
            if (data.success) {
                window.location.href = `${basePath}index.php?ruta=ficha/subir`;
            } else {
                alert('Error al eliminar: ' + (data.error || 'Fallo desconocido'));
                btnEliminarReprocesar.disabled = false;
                btnEliminarReprocesar.innerText = 'Borrar y Reprocesar';
            }
        } catch (e) {
            alert('Error de conexión.');
            btnEliminarReprocesar.disabled = false;
            btnEliminarReprocesar.innerText = 'Borrar y Reprocesar';
        }
    });

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
});
