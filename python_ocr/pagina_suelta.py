"""Arma una pagina HTML que funciona sola, sin Python ni servidor.

Lleva las imagenes de las cedulas incrustadas y los datos adentro, asi que se
abre con doble clic, se puede corregir, y guarda lo corregido en el propio
navegador. Sirve para revisar en otro equipo o para entregar el trabajo.
"""
import base64
import io
import json
import os

from cotejo import CAMPOS

ANCHO_MAX = 1100      # las imagenes se achican un poco: el archivo pesaria de mas
CALIDAD = 68

TITULOS = {
    "tipo_documento": "Tipo de documento",
    "documento": "Número de documento",
    "apellidos": "Apellidos",
    "nombres": "Nombres",
    "nacimiento": "Fecha de nacimiento",
    "rh": "Sangre (RH)",
    "ciudad": "Ciudad de nacimiento",
    "departamento": "Departamento",
    "expedicion": "Fecha de expedición",
    "lugar_expedicion": "Lugar de expedición",
}


def _imagen_incrustada(ruta):
    """Achica y recomprime la imagen, y la devuelve lista para incrustar."""
    from PIL import Image
    img = Image.open(ruta)
    img = img.convert("RGB")
    if max(img.size) > ANCHO_MAX:
        img.thumbnail((ANCHO_MAX, ANCHO_MAX), Image.LANCZOS)
    buf = io.BytesIO()
    img.save(buf, format="JPEG", quality=CALIDAD)
    return "data:image/jpeg;base64," + base64.b64encode(buf.getvalue()).decode("ascii")


def construir(datos, carpeta_img):
    """Devuelve el HTML completo, en bytes."""
    personas = []
    cache = {}
    for per in datos["personas"]:
        paginas = []
        for p in per["paginas"]:
            nombre = p["imagen"]
            if nombre not in cache:
                ruta = os.path.join(carpeta_img, nombre)
                cache[nombre] = _imagen_incrustada(ruta) if os.path.exists(ruta) else ""
            # el tamano original hace falta para ubicar los recuadros de resaltado
            paginas.append({"pagina": p["pagina"], "img": cache[nombre],
                            "ancho": p.get("ancho"), "alto": p.get("alto")})

        # de donde salio cada dato, para poder señalarlo sobre la imagen
        fuentes = {}
        for c in CAMPOS:
            d = (per.get("campos") or {}).get(c) or {}
            fuentes[c] = {"origen": d.get("origen", []), "pagina": d.get("pagina"),
                          "alternativas": d.get("alternativas", [])}

        personas.append({
            "id": per["id"],
            "estado": per["estado"],
            "origen": per.get("origen", ""),
            "novedad": per.get("novedad", ""),
            "revisada": bool(per.get("revisada")),
            "valores": per["valores"],
            "comparacion": per.get("comparacion", {}),
            "fuentes": fuentes,
            "paginas": paginas,
        })

    paquete = {
        "archivo": datos.get("archivo", ""),
        "creado": datos.get("creado", ""),
        "listado": datos.get("listado", ""),
        "total_paginas": datos.get("total_paginas", 0),
        "campos": list(CAMPOS),
        "titulos": TITULOS,
        "personas": personas,
        "faltantes": datos.get("faltantes", []),
    }
    crudo = json.dumps(paquete, ensure_ascii=False).replace("</", "<\\/")
    return PLANTILLA.replace("/*DATOS*/", crudo).encode("utf-8")


PLANTILLA = r"""<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Revision de cedulas</title>
<style>
:root{--fondo:#eef1f6;--papel:#fff;--papel-2:#f7f9fc;--borde:#dde3ec;
--texto:#16202b;--texto-2:#5a6675;--suave:#8592a3;
--azul:#2563c9;--azul-2:#1b4fa8;--azul-claro:#e8f0fd;--rojo:#c62f2f;--rojo-claro:#fdeded;
--ambar:#96650a;--ambar-claro:#fff4dd;--verde:#157a4c;--verde-claro:#e4f5ec;
--visor:#2a3038;--visor-2:#21262d;--radio:10px;--radio-ch:7px;}
html[data-tema="oscuro"]{--fondo:#12161c;--papel:#1a1f27;--papel-2:#212832;--borde:#2c3542;
--texto:#e6ecf3;--texto-2:#a5b2c2;--suave:#7d8a9b;
--azul:#5b9bff;--azul-2:#7db0ff;--azul-claro:#1c2a3f;--rojo:#ff7b7b;--rojo-claro:#37211f;
--ambar:#f0be5e;--ambar-claro:#33290f;--verde:#5cd39a;--verde-claro:#142d22;
--visor:#0d1117;--visor-2:#090c10;}
*{box-sizing:border-box}
[hidden]{display:none!important}
body{margin:0;font-family:"Segoe UI",system-ui,sans-serif;background:var(--fondo);
color:var(--texto);font-size:15px;height:100vh;overflow:hidden}
#app{display:flex;flex-direction:column;height:100vh}
.cab{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;
background:var(--papel);border-bottom:1px solid var(--borde);padding:.7rem 1.2rem}
.cab h1{font-size:1.05rem;margin:0 0 .15rem}
.res{margin:0;font-size:.83rem;color:var(--suave)}
.acc{display:flex;align-items:center;gap:.5rem}
button.b{background:var(--azul);color:#fff;border:0;border-radius:7px;
padding:.5rem .9rem;font-size:.86rem;font-weight:600;cursor:pointer}
button.b.sec{background:var(--papel);color:var(--azul);border:1px solid var(--borde)}
.guardado{font-size:.78rem;color:var(--verde);min-width:7rem;text-align:right}
.cuerpo{display:flex;flex:1;min-height:0}
.lista{width:260px;flex-shrink:0;background:var(--papel);border-right:1px solid var(--borde);
overflow-y:auto}
.filtros{display:flex;gap:.25rem;padding:.55rem;border-bottom:1px solid var(--borde)}
.filtros button{flex:1;border:1px solid var(--borde);background:var(--papel);color:var(--suave);
border-radius:6px;padding:.32rem .2rem;font-size:.73rem;cursor:pointer}
.filtros button.on{background:var(--azul-claro);color:var(--azul);border-color:var(--azul);font-weight:600}
ul.per{list-style:none;margin:0;padding:0}
ul.per li{padding:.5rem .75rem;border-bottom:1px solid var(--borde);cursor:pointer;
border-left:3px solid transparent}
ul.per li:hover{background:var(--papel-2)}
ul.per li.on{background:var(--azul-claro);border-left-color:var(--azul)}
ul.per li.off{display:none}
.nom{font-weight:600;font-size:.86rem}
.doc{color:var(--suave);font-size:.77rem;display:flex;justify-content:space-between;gap:.4rem}
.pt{width:8px;height:8px;border-radius:50%;display:inline-block;margin-right:.35rem}
.pt.ok{background:var(--verde)}.pt.revisar{background:var(--rojo)}
.pt.sin_listado{background:var(--ambar)}
.falta{padding:.85rem .75rem;border-top:1px solid var(--borde);background:var(--papel-2)}
.falta h3{font-size:.83rem;margin:0 0 .3rem}
.falta ul{list-style:none;margin:0;padding:0;font-size:.79rem}
.falta li{padding:.22rem 0}
.falta li span{color:var(--suave);display:block;font-size:.74rem}
.det{flex:1;display:flex;min-width:0}
.visor{flex:1;display:flex;flex-direction:column;min-width:0;background:var(--visor)}
.vbar{display:flex;justify-content:space-between;align-items:center;gap:.5rem;
padding:.35rem .6rem;background:var(--visor-2)}
.vbar button{background:rgba(255,255,255,.09);color:#cfd8e3;border:0;border-radius:5px;
padding:.27rem .6rem;font-size:.78rem;cursor:pointer}
.vbar button.on{background:var(--azul);color:#fff}
.tabs{display:flex;gap:.3rem;flex-wrap:wrap}
.lienzo{flex:1;overflow:auto;display:flex;align-items:flex-start;justify-content:center;padding:.8rem}
.marco{position:relative;display:inline-block;line-height:0;border-radius:4px;
overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.35)}
.marco img{display:block;max-width:100%;height:auto;background:#fff}
.cajas{position:absolute;inset:0;pointer-events:none}
.caja{position:absolute;border:2px solid #ffd23f;border-radius:3px;
background:rgba(255,210,63,.18);box-shadow:0 0 0 9999px rgba(0,0,0,.42)}
.pista{margin:0;padding:.4rem .8rem;background:var(--visor-2);color:#8b97a6;
font-size:.74rem;text-align:center}
.campos{width:400px;flex-shrink:0;background:var(--papel);border-left:1px solid var(--borde);
display:flex;flex-direction:column;overflow-y:auto}
.ch{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.8rem 1rem .2rem}
.ch h2{font-size:.98rem;margin:0}
.chip{font-size:.71rem;padding:.16rem .5rem;border-radius:20px;font-weight:600;white-space:nowrap}
.chip.ok{background:var(--verde-claro);color:var(--verde)}
.chip.revisar{background:var(--rojo-claro);color:var(--rojo)}
.chip.sin_listado{background:var(--ambar-claro);color:var(--ambar)}
.expl{padding:0 1rem;color:var(--suave);font-size:.82rem;margin:.3rem 0}
.lc{padding:.4rem 1rem 1rem;flex:1}
.f{margin-bottom:.95rem}
.f label{display:block;font-weight:600;font-size:.79rem;margin-bottom:.22rem}
.f input[type=text]{width:100%;padding:.48rem .6rem;font-size:.94rem;
border:1px solid var(--borde);border-radius:7px}
.f input:focus{outline:2px solid var(--azul);border-color:var(--azul)}
.f.difiere input{border-color:var(--rojo);background:var(--rojo-claro)}
.f.no_leido input{border-color:var(--ambar);background:var(--ambar-claro)}
.cmp{margin-top:.28rem;font-size:.77rem;display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}
.cmp .rot{color:var(--suave)}
.cmp .val{font-weight:600}
.cmp.difiere .val{color:var(--rojo)}
.usar{background:var(--azul-claro);color:var(--azul);border:1px solid var(--azul);
border-radius:5px;padding:.1rem .45rem;font-size:.71rem;cursor:pointer}
.alt{font-size:.74rem;color:var(--suave);margin-top:.22rem}
.alt button{background:none;border:0;color:var(--azul);cursor:pointer;
text-decoration:underline;font-size:.74rem;padding:0 .2rem}
.pie{border-top:1px solid var(--borde);padding:.75rem 1rem}
.nav{display:flex;gap:.5rem;margin-top:.6rem}
.nav button{flex:1;padding:.45rem;border:1px solid var(--borde);background:var(--papel);
border-radius:7px;cursor:pointer;font-size:.84rem}
.nav button:hover{background:var(--azul-claro)}
.desc{display:flex;align-items:center;gap:.45rem;font-size:.81rem;color:var(--suave)}
@media(max-width:1100px){.det{flex-direction:column}.campos{width:auto;border-left:0;
border-top:1px solid var(--borde)}}
</style>
</head>
<body>
<div id="app">
  <header class="cab">
    <div>
      <h1 id="tit"></h1>
      <p class="res" id="res"></p>
    </div>
    <div class="acc">
      <span class="guardado" id="guard"></span>
      <button class="b" id="csv">Descargar CSV</button>
      <button class="b sec" id="tema" title="Claro / oscuro">Tema</button>
      <button class="b sec" id="limpiar">Deshacer mis cambios</button>
    </div>
  </header>
  <div class="cuerpo">
    <nav class="lista">
      <div class="filtros">
        <button class="on" data-f="todos">Todas</button>
        <button data-f="revisar">Diferencias</button>
        <button data-f="sin_listado">Sin listado</button>
      </div>
      <ul class="per" id="per"></ul>
      <div class="falta" id="fcaja" hidden>
        <h3>Inscritos sin c&eacute;dula</h3>
        <ul id="falta"></ul>
      </div>
    </nav>
    <section class="det">
      <div class="visor">
        <div class="vbar">
          <div class="tabs" id="tabs"></div>
          <div><button id="menos">&minus;</button> <button id="mas">+</button>
               <button id="fit">Ajustar</button></div>
        </div>
        <div class="lienzo">
          <div class="marco" id="marco">
            <img id="img" alt="C&eacute;dula">
            <div class="cajas" id="cajas"></div>
          </div>
        </div>
        <p class="pista">P&aacute;rate en un campo y te se&ntilde;alo d&oacute;nde lo ley&oacute; el OCR</p>
      </div>
      <form class="campos" id="form" autocomplete="off" onsubmit="return false">
        <div class="ch"><h2 id="nomp"></h2><span class="chip" id="chip"></span></div>
        <p class="expl" id="expl"></p>
        <div class="lc" id="lc"></div>
        <div class="pie">
          <label class="desc"><input type="checkbox" id="desc">
            <span>No es una c&eacute;dula / no incluir en el CSV</span></label>
          <div class="nav">
            <button type="button" id="ant">&larr; Anterior</button>
            <button type="button" id="sig">Siguiente &rarr;</button>
          </div>
        </div>
      </form>
    </section>
  </div>
</div>
<script>
const D = /*DATOS*/;

// tema claro u oscuro, siguiendo lo que use el sistema salvo que se cambie a mano
(function () {
  const g = localStorage.getItem('tema-cedulas');
  const oscuro = g ? g === 'oscuro'
    : window.matchMedia('(prefers-color-scheme: dark)').matches;
  document.documentElement.dataset.tema = oscuro ? 'oscuro' : 'claro';
}());

const LLAVE = 'cedulas:' + (D.archivo || '') + ':' + (D.creado || '');
let actual = 0, escala = 1, filtro = 'todos';

// Lo corregido se guarda en el propio navegador, para no perderlo al cerrar.
let guardado = {};
try { guardado = JSON.parse(localStorage.getItem(LLAVE) || '{}'); } catch (e) { guardado = {}; }
D.personas.forEach((p) => {
  const g = guardado[p.id];
  if (g) {
    D.campos.forEach((c) => { if (g[c] !== undefined) p.valores[c] = g[c]; });
    if (g.descartada !== undefined) p.descartada = g.descartada;
    p.editado = true;
  }
});

const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g,
  (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const nombreDe = (p) => (`${p.valores.nombres || ''} ${p.valores.apellidos || ''}`).trim() || '(sin nombre)';

function guardar() {
  const m = {};
  D.personas.forEach((p) => {
    if (!p.editado && !p.descartada) return;
    const o = {};
    D.campos.forEach((c) => { o[c] = p.valores[c]; });
    o.descartada = !!p.descartada;
    m[p.id] = o;
  });
  try {
    localStorage.setItem(LLAVE, JSON.stringify(m));
    document.getElementById('guard').textContent = 'Guardado aquí mismo';
  } catch (e) {
    document.getElementById('guard').textContent = 'No pude guardar';
  }
}

function resumen() {
  const c = (e) => D.personas.filter((x) => x.estado === e).length;
  const t = [`${D.personas.length} cédulas en ${D.total_paginas} páginas`,
             `${c('ok')} coinciden`];
  if (c('revisar')) t.push(`${c('revisar')} con diferencias`);
  if (c('sin_listado')) t.push(`${c('sin_listado')} sin listado`);
  if ((D.faltantes || []).length) t.push(`${D.faltantes.length} inscritos sin cédula`);
  document.getElementById('res').textContent = t.join(' · ');
}

function pintarLista() {
  const ul = document.getElementById('per');
  ul.innerHTML = '';
  D.personas.forEach((p, i) => {
    const li = document.createElement('li');
    li.dataset.i = i; li.dataset.e = p.estado;
    li.innerHTML = `<div class="nom"><span class="pt ${p.estado}"></span>${esc(nombreDe(p))}</div>
      <div class="doc"><span>${esc(p.valores.documento || 'sin número')}</span>
      <span>pág. ${p.paginas.map((x) => x.pagina).join(', ')}</span></div>`;
    li.onclick = () => mostrar(i);
    ul.appendChild(li);
  });
  aplicarFiltro();
}

function aplicarFiltro() {
  document.querySelectorAll('#per li').forEach((li) => {
    li.classList.toggle('off', filtro !== 'todos' && li.dataset.e !== filtro);
  });
}

function pintarFaltantes() {
  if (!(D.faltantes || []).length) return;
  document.getElementById('fcaja').hidden = false;
  const ul = document.getElementById('falta');
  D.faltantes.forEach((f) => {
    const li = document.createElement('li');
    li.innerHTML = `${esc(f.nombre_completo)}<span>${esc(f.documento)}</span>`;
    ul.appendChild(li);
  });
}

function mostrar(i) {
  actual = i; escala = 1;
  const p = D.personas[i];
  document.querySelectorAll('#per li').forEach((li) => {
    li.classList.toggle('on', Number(li.dataset.i) === i);
  });
  const a = document.querySelector('#per li.on');
  if (a) a.scrollIntoView({ block: 'nearest' });

  document.getElementById('nomp').textContent = nombreDe(p);
  const chip = document.getElementById('chip');
  chip.textContent = { ok: 'Coincide', revisar: 'Revisar',
                       sin_listado: 'No está en el listado' }[p.estado] || p.estado;
  chip.className = 'chip ' + p.estado;
  document.getElementById('expl').textContent = p.estado === 'sin_listado'
    ? 'No aparece en el listado. Revisa el número, o márcala abajo si no es una cédula.'
    : p.estado === 'revisar'
      ? 'Lo que leyó el OCR no coincide con el listado en lo marcado en rojo.'
      : 'Coincide con el listado en todos los campos comparables.';
  document.getElementById('desc').checked = !!p.descartada;

  const tabs = document.getElementById('tabs');
  tabs.innerHTML = '';
  p.paginas.forEach((pg, k) => {
    const b = document.createElement('button');
    b.textContent = 'Página ' + pg.pagina;
    b.onclick = () => verPag(k);
    tabs.appendChild(b);
  });
  pintarCampos(p);
  verPag(0);
  senalar('documento');
}

let pagVista = 0;

function limpiarCajas() { document.getElementById('cajas').innerHTML = ''; }

/** Dibuja sobre la imagen el pedazo exacto de donde salio un campo. */
function senalar(campo) {
  const p = D.personas[actual];
  const f = (p.fuentes || {})[campo];
  limpiarCajas();
  if (!f || !f.origen || !f.origen.length) return;

  let k = pagVista;
  if (f.pagina != null) {
    const j = p.paginas.findIndex((x) => x.pagina === f.pagina);
    if (j >= 0) k = j;
  }
  if (k !== pagVista) verPag(k, true);

  const pag = p.paginas[k];
  if (!pag || !pag.ancho || !pag.alto) return;

  const cont = document.getElementById('cajas');
  f.origen.forEach((o) => {
    const d = document.createElement('div');
    d.className = 'caja';
    const m = 4;
    d.style.left = ((o.x - m) / pag.ancho * 100) + '%';
    d.style.top = ((o.y - m) / pag.alto * 100) + '%';
    d.style.width = ((o.w + m * 2) / pag.ancho * 100) + '%';
    d.style.height = ((o.h + m * 2) / pag.alto * 100) + '%';
    cont.appendChild(d);
  });
}

function verPag(k, conservar) {
  const p = D.personas[actual];
  if (!p.paginas[k]) return;
  pagVista = k;
  if (!conservar) limpiarCajas();
  document.getElementById('img').src = p.paginas[k].img;
  document.querySelectorAll('#tabs button').forEach((b, j) => b.classList.toggle('on', j === k));
  zoom();
}

function zoom() {
  const im = document.getElementById('img');
  im.style.maxWidth = escala === 1 ? '100%' : 'none';
  im.style.width = escala === 1 ? 'auto' : (escala * 100) + '%';
}

function pintarCampos(p) {
  const lc = document.getElementById('lc');
  lc.innerHTML = '';
  D.campos.forEach((c) => {
    const cmp = (p.comparacion || {})[c] || {};
    const f = document.createElement('div');
    f.className = 'f ' + (cmp.estado || '');
    f.innerHTML = `<label>${esc(D.titulos[c] || c)}</label>
      <input type="text" data-c="${c}" value="${esc(p.valores[c] || '')}">`;

    if (cmp.esperado) {
      const caja = document.createElement('div');
      caja.className = 'cmp ' + (cmp.estado === 'difiere' ? 'difiere' : '');
      caja.innerHTML = `<span class="rot">Listado:</span><span class="val">${esc(cmp.esperado)}</span>`;
      if (cmp.estado === 'difiere' || cmp.estado === 'no_leido') {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'usar'; b.textContent = 'Usar este';
        b.onclick = () => { const i = f.querySelector('input'); i.value = cmp.esperado;
                            i.dispatchEvent(new Event('input', { bubbles: true })); };
        caja.appendChild(b);
      }
      f.appendChild(caja);
    }

    const alts = ((p.fuentes || {})[c] || {}).alternativas || [];
    if (alts.length) {
      const d = document.createElement('div');
      d.className = 'alt';
      d.appendChild(document.createTextNode('El OCR también leyó: '));
      alts.forEach((v) => {
        const b = document.createElement('button');
        b.type = 'button'; b.textContent = v;
        b.onclick = () => { const i = f.querySelector('input'); i.value = v;
                            i.dispatchEvent(new Event('input', { bubbles: true })); };
        d.appendChild(b);
      });
      f.appendChild(d);
    }
    lc.appendChild(f);
  });

  // pararse en un campo lo señala sobre la imagen
  lc.querySelectorAll('.f').forEach((f) => {
    const campo = f.querySelector('input').dataset.c;
    f.querySelector('input').addEventListener('focus', () => senalar(campo));
    f.addEventListener('mouseenter', () => senalar(campo));
  });

  lc.querySelectorAll('input[data-c]').forEach((inp) => {
    inp.addEventListener('input', () => {
      const q = D.personas[actual];
      q.valores[inp.dataset.c] = inp.value;
      q.editado = true;
      document.getElementById('nomp').textContent = nombreDe(q);
      const li = document.querySelector(`#per li[data-i="${actual}"]`);
      if (li) {
        li.querySelector('.nom').innerHTML =
          `<span class="pt ${q.estado}"></span>${esc(nombreDe(q))}`;
        li.querySelector('.doc span').textContent = q.valores.documento || 'sin número';
      }
      guardar();
    });
  });
}

document.getElementById('desc').onchange = (e) => {
  D.personas[actual].descartada = e.target.checked; guardar();
};
document.getElementById('ant').onclick = () => { if (actual > 0) mostrar(actual - 1); };
document.getElementById('sig').onclick = () => {
  if (actual < D.personas.length - 1) mostrar(actual + 1);
};
document.getElementById('mas').onclick = () => { escala = Math.min(escala * 1.3, 6); zoom(); };
document.getElementById('menos').onclick = () => { escala = Math.max(escala / 1.3, 1); zoom(); };
document.getElementById('fit').onclick = () => { escala = 1; zoom(); };
document.querySelectorAll('.filtros button').forEach((b) => {
  b.onclick = () => {
    document.querySelectorAll('.filtros button').forEach((x) => x.classList.remove('on'));
    b.classList.add('on'); filtro = b.dataset.f; aplicarFiltro();
  };
});
document.getElementById('tema').onclick = () => {
  const n = document.documentElement.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';
  document.documentElement.dataset.tema = n;
  localStorage.setItem('tema-cedulas', n);
};
document.getElementById('limpiar').onclick = () => {
  if (!confirm('Se borran las correcciones que hiciste en esta página. ¿Seguro?')) return;
  localStorage.removeItem(LLAVE); location.reload();
};
document.getElementById('csv').onclick = () => {
  const enc = D.campos.map((c) => D.titulos[c] || c)
    .concat(['Estado', 'Coincide con el listado', 'Páginas', 'Revisado a mano']);
  const filas = [enc];
  D.personas.filter((p) => !p.descartada).forEach((p) => {
    const difs = D.campos.filter((c) => (p.comparacion[c] || {}).estado === 'difiere')
      .map((c) => D.titulos[c] || c);
    const cruce = (p.comparacion.documento || {}).esperado
      ? (difs.length ? 'Difiere en: ' + difs.join(', ') : 'Sí')
      : 'No está en el listado';
    filas.push(D.campos.map((c) => p.valores[c] || '')
      .concat([p.estado, cruce, p.paginas.map((x) => x.pagina).join(', '),
               p.editado ? 'Sí' : 'No']));
  });
  const texto = '﻿' + filas.map((f) => f.map((v) =>
    /[;"\n]/.test(v) ? '"' + String(v).replace(/"/g, '""') + '"' : v).join(';')).join('\n');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([texto], { type: 'text/csv;charset=utf-8' }));
  a.download = (D.archivo || 'cedulas').replace(/\.[^.]+$/, '') + ' - datos.csv';
  a.click();
};

document.getElementById('tit').textContent = D.archivo || 'Revisión de cédulas';
resumen(); pintarLista(); pintarFaltantes();
if (D.personas.length) mostrar(0);
if (Object.keys(guardado).length) {
  document.getElementById('guard').textContent = 'Con tus correcciones';
}
</script>
</body>
</html>
"""
