#!/bin/sh
#
# Quita el guion "—" que se muestra como PLACEHOLDER de campo vacío y deja el campo
# en blanco. Actúa SOLO sobre dos patrones inequívocos de placeholder:
#
#     {{ $campo ?? '—' }}            →   {{ $campo ?? '' }}
#     {{ $x ? ...format... : '—' }}  →   {{ $x ? ...format... : '' }}
#
# NO toca los guiones legítimos —opciones "— Seleccione —", frases como
# "folio — actualice", "Nivel calculado: —"— porque esos nunca vienen precedidos
# justo por "?? " o ": " pegados al guion.
#
# Uso (desde la raíz del proyecto, dentro del contenedor):
#     docker compose exec app sh scripts/quitar-guiones-vacios.sh
#
# Revísalo después con `git diff` antes de confirmar; es reversible con git.

set -e

echo "Antes:"
echo "  ?? '—' : $(grep -rIn "?? '—'" resources/views | wc -l)"
echo "  : '—'  : $(grep -rIn ": '—'" resources/views | wc -l)"

# El placeholder con null-coalescing.
grep -rIl "?? '—'" resources/views | xargs -r sed -i "s/?? '—'/?? ''/g"

# El placeholder en ternarios (: '—' al final).
grep -rIl ": '—'" resources/views | xargs -r sed -i "s/: '—'/: ''/g"

echo "Después:"
echo "  ?? '—' restantes : $(grep -rIn "?? '—'" resources/views | wc -l)"
echo "  : '—' restantes  : $(grep -rIn ": '—'" resources/views | wc -l)"
echo "  legítimos intactos (— Seleccione, etc): $(grep -rIn "Seleccione\|Registro en general\|Elija\|Nivel calculado" resources/views | grep -c "—" || true)"
echo
echo "Listo. Revisa con: git diff"
