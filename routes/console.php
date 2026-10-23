<?php

use Illuminate\Support\Facades\Schedule;

// Agendador da VPS (container central_agendador, "php artisan schedule:work"). Horários de Brasília.
Schedule::command('central:coletar conta')->dailyAt('06:00')->withoutOverlapping(60);
Schedule::command('central:coletar posts')->dailyAt('06:15')->withoutOverlapping(60);
Schedule::command('central:coletar semanal')->weeklyOn(1, '06:40')->withoutOverlapping(60);
Schedule::command('central:coletar comentarios')->cron('0 8-23/3 * * *')->withoutOverlapping(60);
