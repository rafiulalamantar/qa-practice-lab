<?php

// Vercel serverless entry point: every non-static request is routed here
// (see vercel.json) and handed to Laravel's normal front controller.
require __DIR__.'/../public/index.php';
