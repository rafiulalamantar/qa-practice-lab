<?php

// Vercel serverless entry point: every non-static request is routed here
// (see vercel.json) and handed to Laravel's normal front controller.

// Vercel reports this file as the script, so Laravel would treat "/api" as
// the app's base path and strip it from every URL, turning /api/ecom/... into
// /ecom/... and 404ing. Present the app as served from the site root instead.
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';

require __DIR__.'/../public/index.php';
