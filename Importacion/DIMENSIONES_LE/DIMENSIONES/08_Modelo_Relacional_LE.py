"""Modelo estrella LE con role-playing: una tabla de establecimiento por cada rol."""
import graphviz
AZUL, NARANJO, GRIS, VERDE, MORADO = '#1F4E78', '#C55A11', '#595959', '#375623', '#7030A0'
g = graphviz.Digraph('estrella', format='png', engine='neato')
g.attr(bgcolor='white', fontname='Calibri', overlap='false', splines='true', forcelabels='true', sep='+8',
       label='\\nModelo estrella — Listas de Espera SIGTE × Dimensiones\\nServicio de Salud Chiloé · corte 15-07-2026 · las 3 listas comparten el mismo modelo',
       labelloc='t', fontsize='15', fontcolor=AZUL)
g.attr('node', fontname='Calibri', shape='plaintext')
g.attr('edge', fontname='Calibri', fontsize='9', color=GRIS, arrowsize='0.6', fontcolor=GRIS)

def tabla(nid, tit, sub, filas, color, pos, w=11):
    fs = ''.join(f'<TR><TD ALIGN="LEFT" BGCOLOR="{"#F2F5FA" if i%2 else "white"}"><FONT POINT-SIZE="9">{f}</FONT></TD></TR>'
                 for i, f in enumerate(filas))
    html = f'''<<TABLE BORDER="0" CELLBORDER="1" CELLSPACING="0" CELLPADDING="3">
    <TR><TD BGCOLOR="{color}"><FONT COLOR="white" POINT-SIZE="{w}"><B>{tit}</B></FONT><BR/>
    <FONT COLOR="white" POINT-SIZE="8">{sub}</FONT></TD></TR>{fs}</TABLE>>'''
    g.node(nid, html, pos=pos, pin='true')

tabla('F', 'DEMANDA TOTAL — LISTA DE ESPERA', 'CNE 245.815 · PROC 170.041 · IQ 31.690 filas',
      ['PK   SIGTE_ID', 'FK   RUN + DV', 'FK   PRESTA_MIN', 'FK   ESTAB_ORIG', 'FK   ESTAB_DEST',
       'FK   E_OTOR_AT', 'FK   C_SALIDA', 'FK   TIPO_PREST', 'FK   CIE10_HOMOLOGADO  (derivada)',
       '       F_ENTRADA · F_SALIDA', '       ESTADO · DIAS_ESPERA'], AZUL, '0,0!', w=13)

EST_COLS = ['PK  Código Nuevo (6 díg.)', '      NOMBRE · Nom. Comuna', '      Nivel de Atención', '      SS Codigo (33 = Chiloé)']
D = [
 ('D_PAC', 'Maestro_Pacientes_Unificado', '290.253 pacientes', ['PK  RUN', '      TELEFONO_RECIENTE', '      EMAILS · COMUNA'], VERDE, '-9.0,3.2!'),
 ('D_PRE', 'Prestaciones_CNE_IQ', '1.311 · CNE + IQ', ['PK  Propuesta de Código / Código SIGTE', '      Nombre de Prestación', '      Especialidad'], NARANJO, '-2.6,6.4!'),
 ('D_MPR', 'Maestro_Prestacion', '1.947 códigos', ['PK  Código', '      Prestación · Especialidad', '      Tipo de lista'], NARANJO, '4.4,6.4!'),
 ('D_ORI', 'Dim_Establecimiento_ORIGEN', 'quién deriva · 2.700', EST_COLS, MORADO, '9.6,2.6!'),
 ('D_DES', 'Dim_Establecimiento_DESTINO', 'quién resuelve · 2.700', EST_COLS, MORADO, '9.8,-1.6!'),
 ('D_OTO', 'Dim_Establecimiento_OTORGANTE', 'quién atendió · 2.700', EST_COLS, MORADO, '8.2,-5.8!'),
 ('D_CIE', 'CIE10', '14.226 códigos', ['PK  CODIGO', '      DESCRIPCION'], NARANJO, '1.6,-7.4!'),
 ('D_CAU', 'Causales_Salidas', '22 causales', ['PK  Causales', '      Nombre de Causal', '      Tipo de Causal'], NARANJO, '-5.0,-6.6!'),
 ('D_TIP', 'Tipo_Lista', '5 tipos', ['PK  Tipo', '      1 CNE · 3 PROC · 4 IQ'], NARANJO, '-9.4,-2.8!'),
]
for nid, t, s, f, c, p in D:
    tabla(nid, t, s, f, c, p)

# nota sobre role-playing
g.node('NOTA', '''<<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="6" BGCOLOR="#F2F0F7" COLOR="#7030A0">
<TR><TD ALIGN="LEFT"><FONT POINT-SIZE="9" COLOR="#7030A0"><B>Role-playing dimension</B></FONT></TD></TR>
<TR><TD ALIGN="LEFT"><FONT POINT-SIZE="8">Las 3 tablas moradas son la MISMA fuente<BR ALIGN="LEFT"/>(Mantenedor_ Establecimiento.xlsx), cargada<BR ALIGN="LEFT"/>una vez por cada rol. No son archivos distintos:<BR ALIGN="LEFT"/>son 3 consultas al mismo origen.<BR ALIGN="LEFT"/><BR ALIGN="LEFT"/>Se separan porque Power BI solo admite UNA<BR ALIGN="LEFT"/>relación activa entre dos tablas: con una sola<BR ALIGN="LEFT"/>dimensión habría que usar USERELATIONSHIP<BR ALIGN="LEFT"/>en cada medida.</FONT></TD></TR></TABLE>>''',
       pos='9.2,6.6!', pin='true')

E = [('F','D_PAC','1','solid'), ('F','D_PRE','2','solid'), ('F','D_MPR','3','solid'),
     ('F','D_ORI','4','solid'), ('F','D_DES','5','solid'), ('F','D_OTO','6','solid'),
     ('F','D_CAU','7','solid'), ('F','D_TIP','8','solid'), ('F','D_CIE','9','dashed')]
for a, b, n, st in E:
    col = '#8FAADC' if st == 'dashed' else (MORADO if b in ('D_ORI','D_DES','D_OTO') else GRIS)
    g.edge(a, b, label=f'  {n}  ', style=st, color=col, fontsize='12', fontcolor=AZUL,
           headlabel=' 1 ', taillabel=' N ', labeldistance='2.4', labelfontsize='8', labelfontcolor='#8C8C8C')

p = g.render(filename='/tmp/08_Modelo_Relacional_LE', cleanup=True)
print('png ->', p)
