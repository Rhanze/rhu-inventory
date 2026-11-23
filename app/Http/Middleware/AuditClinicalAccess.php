<?php

namespace App\Http\Middleware;

use App\Support\ClinicalAudit;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditClinicalAccess
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $response = $next($request);
        if ($response->isSuccessful()) {
            $record = $request->route('id') ?? $request->route('patient');
            $id = filter_var($record instanceof Model ? $record->getKey() : $record, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]);
            $action = match (true) {
                $resource === 'prescription' => 'Download',
                $request->routeIs('*.csv', '*.export') => 'Export',
                default => 'Read',
            };
            ClinicalAudit::record($action, $resource, $id ?: null);
        }

        return $response;
    }
}
