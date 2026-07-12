<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La puerta principal de PUNTA está cerrada.
 *
 * ── Qué había aquí antes ─────────────────────────────────────────────
 *
 * El ExampleTest que trae Laravel de fábrica: pedía '/' y esperaba un 200, que es lo que
 * devuelve la página de bienvenida de un Laravel recién instalado.
 *
 * PUNTA no tiene página pública: '/' redirige al login. Así que esa prueba llevaba roja desde
 * el primer día del proyecto, y no comprobaba nada del sistema — solo que Laravel seguía
 * siendo Laravel.
 *
 * ── Por qué eso importaba más de lo que parece ───────────────────────
 *
 * Una prueba que SIEMPRE está roja es peor que ninguna prueba.
 *
 * La gente aprende a ignorar el rojo. "Ah, esa siempre falla." Y el día que se ponga roja otra
 * —una que sí importe— ese hábito hará que también se ignore. El ruido no solo no informa:
 * destruye la señal de todo lo que tiene alrededor.
 *
 * Es lo mismo que pasa con un aviso que sale en todas las pantallas: deja de ser un aviso.
 *
 * ── Qué hace ahora ───────────────────────────────────────────────────
 *
 * Afirma lo que PUNTA sí hace. Y de paso cubre una regla de seguridad real, que hasta ahora
 * nadie comprobaba: un visitante sin sesión no entra por la puerta principal.
 */
class EntradaSinSesionTest extends TestCase
{
    public function test_un_visitante_sin_sesion_es_enviado_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
