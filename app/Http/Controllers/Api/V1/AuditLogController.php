<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends BaseApiController
{
    /**
     * Get paginated audit logs for the authenticated business.
     * GET /api/v1/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        $business_id = $this->getBusinessId($request);

        $query = DB::table('activity_log as a')
            ->leftJoin('users as u', 'a.causer_id', '=', 'u.id')
            ->where('a.business_id', $business_id)
            ->select([
                'a.id',
                'a.log_name',
                'a.description',
                'a.subject_id',
                'a.subject_type',
                'a.event',
                'a.causer_id',
                'u.first_name',
                'u.last_name',
                'u.username',
                'a.properties',
                'a.created_at',
            ]);

        // Optional filtering by event / description
        if ($request->filled('event')) {
            $query->where('a.event', $request->input('event'));
        }

        if ($request->filled('description')) {
            $query->where('a.description', 'like', '%' . $request->input('description') . '%');
        }

        if ($request->filled('subject_type')) {
            $query->where('a.subject_type', 'like', '%' . $request->input('subject_type') . '%');
        }

        if ($request->filled('start_date')) {
            $query->whereDate('a.created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('a.created_at', '<=', $request->input('end_date'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $paginator = $query->orderBy('a.id', 'desc')->paginate($perPage);

        // Format items cleanly
        $items = collect($paginator->items())->map(function ($row) {
            $causerName = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
            if (empty($causerName)) {
                $causerName = $row->username ?? 'System';
            }

            $properties = [];
            if (!empty($row->properties)) {
                $properties = json_decode($row->properties, true) ?? [];
            }

            // Extract short class name for subject
            $subjectClass = $row->subject_type ? class_basename($row->subject_type) : null;

            return [
                'id' => $row->id,
                'log_name' => $row->log_name,
                'description' => $row->description,
                'subject_id' => $row->subject_id,
                'subject_type' => $subjectClass,
                'event' => $row->event ?? $row->description,
                'causer_id' => $row->causer_id,
                'causer_name' => $causerName,
                'properties' => $properties,
                'created_at' => $row->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Audit logs retrieved successfully',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}

