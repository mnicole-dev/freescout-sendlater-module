<?php

// Pas de dépendances composer. Charge uniquement les routes du module.
if (!app()->routesAreCached()) {
    require __DIR__ . '/Http/routes.php';
}
