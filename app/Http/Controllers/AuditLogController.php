<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\AuditLogResource;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\ExtendActivity;
use Illuminate\Support\Facades\Http;
use App\Models\Forwarder;
use App\Helpers\UserHelper;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/audit-logs",
     *     tags={"AuditLogs"},
     *     summary="Lists all audit logs",
     *     operationId="auditLogList",
     *     @OA\Response(
     *         response="200",
     *         description="successful operation"
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource Not Found"),
     *     @OA\Response(response=401, description="Authentication is required"),
     *     security={
     *         {
     *             "oauth2_security": {}
     *         }
     *     },
     * )
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $data = $request->all();

        $auditLogs = ExtendActivity::latest('created_at');

        if (!empty($data['search_value'])) {
            $searchValue = $data['search_value'];
            $therapistByName = Http::withToken(
                Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
            )->get(env('ADMIN_SERVICE_URL') . '/internal/user/by-name', ['name' => $searchValue])
            ->json('data', []);
            $adminByName = Http::withToken(
                Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE)
            )->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-name', ['name' => $searchValue])
            ->json('data', []);

            $adminIds = collect($adminByName)->pluck('id')->toArray();
            $therapistIds = collect($therapistByName)->pluck('id')->toArray();
            $auditLogs->where(function ($query) use ($searchValue, $adminIds, $therapistIds) {
                $query->whereHas('user', function ($q) use ($searchValue) {
                    $q->where(function ($q) use ($searchValue) {
                        $q->where('identity', 'like', '%' . $searchValue . '%');
                    });
                })
                ->whereNotIn('log_name', [ExtendActivity::ADMIN_SERVICE, ExtendActivity::THERAPIST_SERVICE]);

                if (!empty($adminIds)) {
                    $query->orWhere(function ($q) use ($adminIds) {
                        $q->where('log_name', ExtendActivity::ADMIN_SERVICE)
                        ->whereIn('causer_id', $adminIds);
                    });
                }

                if (!empty($therapistIds)) {
                    $query->orWhere(function ($q) use ($therapistIds) {
                        $q->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                        ->whereIn('causer_id', $therapistIds);
                    });
                }
            });
        }

        if (!empty($data['filters'])) {
            $filters = $request->get('filters');
            $auditLogs->where(function ($query) use ($filters) {
                foreach ($filters as $filter) {
                    $filterObj = json_decode($filter);

                    if ($filterObj->columnName === 'type_of_changes') {
                        $query->where('description', $filterObj->value);
                    } elseif ($filterObj->columnName === 'who') {
                        $adminByName = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE)
                        )->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-name', ['name' => $filterObj->value])
                        ->json('data', []);
                        $adminIds = collect($adminByName)->pluck('id')->toArray();

                        $therapistByName = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
                        )->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-name', ['name' => $filterObj->value])
                        ->json('data', []);
                        $therapistIds = collect($therapistByName)->pluck('id')->toArray();

                        $query->where(function ($subQuery) use ($filterObj, $adminIds, $therapistIds) {
                            $subQuery->whereHas('user', function ($q) use ($filterObj) {
                                $q->where(function ($q) use ($filterObj) {
                                    $q->where('identity', 'like', '%' . $filterObj->value . '%');
                                });
                            })
                            ->whereNotIn('log_name', [ExtendActivity::ADMIN_SERVICE, ExtendActivity::THERAPIST_SERVICE]);

                            if (!empty($adminIds)) {
                                $subQuery->orWhere(function ($q) use ($adminIds) {
                                    $q->where('log_name', ExtendActivity::ADMIN_SERVICE)
                                    ->whereIn('causer_id', $adminIds);
                                });
                            }

                            if (!empty($therapistIds)) {
                                $subQuery->orWhere(function ($q) use ($therapistIds) {
                                    $q->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                                    ->whereIn('causer_id', $therapistIds);
                                });
                            }
                        });
                    } elseif ($filterObj->columnName === 'user_group') {
                        $adminByGroup = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE)
                        )->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-type', ['type' => $filterObj->value])
                        ->json('data', []);
                        $therapistByGroup = Http::withToken(
                            Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE)
                        )->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-type', ['type' => $filterObj->value])
                        ->json('data', []);
                        $adminIds = collect($adminByGroup)->pluck('id')->toArray();
                        $therapistIds = collect($therapistByGroup)->pluck('id')->toArray();

                        $query->where(function ($subQuery) use ($filterObj, $adminIds, $therapistIds) {

                            if ($filterObj->value === User::GROUP_PATIENT) {
                                $subQuery->whereHas('user')
                                    ->whereNotIn('log_name', [
                                        ExtendActivity::ADMIN_SERVICE,
                                        ExtendActivity::THERAPIST_SERVICE
                                    ]);
                            } else {
                                $subQuery->when(!empty($adminIds), function ($q) use ($adminIds) {
                                    $q->where(function ($q2) use ($adminIds) {
                                        $q2->where('log_name', ExtendActivity::ADMIN_SERVICE)
                                        ->whereIn('causer_id', $adminIds);
                                    });
                                });

                                $subQuery->when(!empty($therapistIds), function ($q) use ($therapistIds) {
                                    $q->orWhere(function ($q2) use ($therapistIds) {
                                        $q2->where('log_name', ExtendActivity::THERAPIST_SERVICE)
                                        ->whereIn('causer_id', $therapistIds);
                                    });
                                });
                            }
                        });
                    } elseif ($filterObj->columnName === 'country') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('country_id', $filterObj->value);
                            })
                            ->orWhere('country_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'region') {
                        $adminIds = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
                        ->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-regions', ['region_ids' => [$filterObj->value]])
                        ->json('data', []);

                        $query->where(function ($subQuery) use ($filterObj, $adminIds) {
                            $subQuery->whereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('region_id', $filterObj->value);
                            })->orWhere('region_id', $filterObj->value);
                            if (!empty($adminIds)) {
                                $subQuery->orWhere(function ($q) use ($adminIds) {
                                    $q->where('log_name', ExtendActivity::ADMIN_SERVICE)
                                    ->whereIn('causer_id', $adminIds);
                                });
                            }
                        });
                    } elseif ($filterObj->columnName === 'province') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user', function ($q) use ($filterObj) {
                                $q->where('province_id', $filterObj->value);
                            })->whereNotIn('log_name', [ExtendActivity::ADMIN_SERVICE, ExtendActivity::THERAPIST_SERVICE]);
                            $subQuery->orWhere('province_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'clinic') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('clinic_id', $filterObj->value);
                            })->whereNotIn('log_name', [ExtendActivity::ADMIN_SERVICE, ExtendActivity::THERAPIST_SERVICE]);
                            $subQuery->orWhere('clinic_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'phc_service') {
                        $query->where(function ($subQuery) use ($filterObj) {
                            $subQuery->orWhereHas('user', function ($subQuery) use ($filterObj) {
                                $subQuery->where('phc_service_id', $filterObj->value);
                            })->whereNotIn('log_name', [ExtendActivity::ADMIN_SERVICE, ExtendActivity::THERAPIST_SERVICE]);
                            $subQuery->orWhere('phc_service_id', $filterObj->value);
                        });
                    } elseif ($filterObj->columnName === 'date_time') {
                        $dates = explode(' - ', $filterObj->value);
                        $startDate = date_create_from_format('d/m/Y', $dates[0]);
                        $endDate = date_create_from_format('d/m/Y', $dates[1]);
                        $startDate->format('Y-m-d');
                        $endDate->format('Y-m-d');
                        $query->whereDate('created_at', '>=', $startDate)
                            ->whereDate('created_at', '<=', $endDate);
                    } elseif ($filterObj->columnName === 'before_changed') {
                        $query->whereRaw("JSON_SEARCH(properties->'$.old', 'one', ?) IS NOT NULL", ["%{$filterObj->value}%"]);
                    } elseif ($filterObj->columnName === 'after_changed') {
                        $query->whereRaw("JSON_SEARCH(properties->'$.attributes', 'one', ?) IS NOT NULL", ["%{$filterObj->value}%"]);
                    } elseif ($filterObj->columnName === 'subject_type') {
                        $query->whereRaw("SUBSTRING_INDEX(subject_type, '\\\\', -1) LIKE ?", ["%{$filterObj->value}%"]);
                    } else {
                        $query->where($filterObj->columnName, 'like', '%' .  $filterObj->value . '%');
                    }
                }
            });
        }

        if ($user->user_type === User::GROUP_ORGANIZATION_ADMIN) {
            $auditLogs->where(function ($query) use ($data) {
                $query->whereHas('user', function ($q) use ($data) {
                    $q->withTrashed()->where('country_id', $data['country_id']);
                })
                ->orWhere('country_id', $data['country_id']);
            });
        } elseif ($user->user_type === User::ADMIN_GROUP_COUNTRY_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->whereHas('user', function ($q) use ($user) {
                    $q->withTrashed()->where('country_id', $user->country_id);
                })
                ->orWhere('country_id', $user->country_id);
            });
        } elseif ($user->user_type === User::ADMIN_GROUP_REGIONAL_ADMIN) {
            $userRegionIds = $user->region_ids ? json_decode($user->region_ids) : [];
            $userByRegions = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-regions', ['region_ids' => $userRegionIds])
            ->json('data', []);
            $auditLogs->where(function ($subQuery) use ($userRegionIds, $userByRegions) {
                $subQuery->whereHas('user', function ($q) use ($userRegionIds) {
                    $q->withTrashed()->whereIn('region_id', $userRegionIds);
                })
                ->orWhereIn('region_id', $userRegionIds)
                ->orwhereIn('causer_id', $userByRegions);
            });
        } elseif ($user->user_type === User::ADMIN_GROUP_CLINIC_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user', function ($subQuery) use ($user) {
                    $subQuery->withTrashed()->where('clinic_id', $user->clinic_id);
                })
                    ->orWhere('clinic_id', $user->clinic_id);
            });
        } elseif ($user->user_type === User::ADMIN_GROUP_PHC_SERVICE_ADMIN) {
            $auditLogs->where(function ($subQuery) use ($user) {
                $subQuery->orWhereHas('user', function ($subQuery) use ($user) {
                    $subQuery->withTrashed()->where('phc_service_id', $user->phc_service_id);
                })
                    ->orWhere('phc_service_id', $user->phc_service_id);
            });
        }

        $auditLogs = $auditLogs->paginate($data['page_size'] ?? 10);
        $info = [
            'current_page' => $auditLogs->currentPage(),
            'total_count' => $auditLogs->total()
        ];
        $adminIds = $auditLogs
            ->where('log_name', ExtendActivity::ADMIN_SERVICE)
            ->pluck('causer_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
        $therapistIds = $auditLogs
            ->where('log_name', ExtendActivity::THERAPIST_SERVICE)
            ->pluck('causer_id')
            ->unique()
            ->filter()
            ->values()
            ->toArray();
        $countryIds = $auditLogs
            ->flatMap(function ($log) {
                return [
                    $log->country_id,
                    optional($log->user)->country_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $regionIds = $auditLogs
            ->flatMap(function ($log) {
                return [
                    $log->region_id,
                    optional($log->user)->region_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $provinceIds = $auditLogs
            ->flatMap(function ($log) {
                return [
                    $log->province_id,
                    optional($log->user)->province_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $clinicIds = $auditLogs
            ->flatMap(function ($log) {
                return [
                    $log->clinic_id,
                    optional($log->user)->clinic_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $phcServiceIds = $auditLogs
            ->flatMap(function ($log) {
                return [
                    $log->phc_service_id,
                    optional($log->user)->phc_service_id,
                ];
            })
            ->filter()
            ->unique()
            ->values()
            ->toArray();
        $adminResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/user/list/by-ids', ['ids' => $adminIds])
            ->json('data', []);
        $therapistResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::THERAPIST_SERVICE))
            ->get(env('THERAPIST_SERVICE_URL') . '/internal/user/by-ids', ['ids' => $therapistIds])
            ->json('data', []);
        $countryResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/country/by-ids', ['ids' => $countryIds])
            ->json('data', []);
        $regionResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/region/by-ids', ['ids' => $regionIds])
            ->json('data', []);
        $provinceResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/province/by-ids', ['ids' => $provinceIds])
            ->json('data', []);
        $clinicResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/clinic/by-ids', ['ids' => $clinicIds])
            ->json('data', []);
        $phcServiceResponse = Http::withToken(Forwarder::getAccessToken(Forwarder::ADMIN_SERVICE))
            ->get(env('ADMIN_SERVICE_URL') . '/internal/phc-service/by-ids', ['ids' => $phcServiceIds])
            ->json('data', []);
        $admins = collect($adminResponse)->keyBy('id');
        $therapists = collect($therapistResponse)->keyBy('id');
        $countries = collect($countryResponse)->keyBy('id');
        $regions = collect($regionResponse)->keyBy('id');
        $provinces = collect($provinceResponse)->keyBy('id');
        $clinics = collect($clinicResponse)->keyBy('id');
        $phcServices = collect($phcServiceResponse)->keyBy('id');
        $auditLogCollection = collect($auditLogs->items());

        $mappedAuditLogs = collect($auditLogCollection)->map(function ($log) use ($admins, $countries, $regions, $provinces, $clinics, $phcServices, $therapists, $user) {
            if ($log->log_name === ExtendActivity::ADMIN_SERVICE) {
                $causer = $admins[$log->causer_id] ?? null;
            } elseif ($log->log_name === ExtendActivity::THERAPIST_SERVICE) {
                $causer = $therapists[$log->causer_id] ?? null;
            } else {
                $causer = $log->user ?? null;
            }

            $regionName = '';
            $causerName = '';
            $country = $countries[$log->country_id ?? data_get($causer, 'country_id')] ?? null;
            $region = $regions[$log->region_id ?? data_get($causer, 'region_id')] ?? null;
            $province = $provinces[$log->province_id ?? data_get($causer, 'province_id')] ?? null;
            $clinic = $clinics[$log->clinic_id ?? data_get($causer, 'clinic_id')] ?? null;
            $phcService = $phcServices[$log->phc_service_id ?? data_get($causer, 'phc_service_id')] ?? null;

            if (data_get($causer, 'regions')) {
                $regionName = collect(data_get($causer, 'regions'))->pluck('name')->join(', ');
            } else {
                $regionName = $region['name'] ?? (($log->region_id || data_get($causer, 'region_id')) ? ExtendActivity::UNKNOWN : '');
            }

            if ($log->log_name === ExtendActivity::PATIENT_SERVICE) {
                $causerName = data_get($causer, 'identity') ?? ExtendActivity::UNKNOWN;
            } else {
                $causerName = $causer ? UserHelper::getFullName(data_get($causer, 'last_name') ?? null, data_get($causer, 'first_name') ?? null, $user->language_id) : ExtendActivity::UNKNOWN;
            }

            $log->causer_name  = $causerName;
            $log->causer_group = $causer['type'] ?? ($log->log_name === ExtendActivity::PATIENT_SERVICE ? User::GROUP_PATIENT : ExtendActivity::UNKNOWN);
            $log->country = $country['name'] ?? ($log->country_id || data_get($causer, 'country_id') ? ExtendActivity::UNKNOWN : '');
            $log->region = $regionName;
            $log->province = $province['name'] ?? (($log->province_id || data_get($causer, 'province_id')) ? ExtendActivity::UNKNOWN : '');
            $log->clinic = $clinic['name'] ?? ($log->clinic_id || data_get($causer, 'clinic_id') ? ExtendActivity::UNKNOWN : '');
            $log->phc_service = $phcService['name'] ?? (($log->phc_service_id || data_get($causer, 'phc_service_id')) ? ExtendActivity::UNKNOWN : '');

            return $log;
        });
        return ['success' => true, 'data' => AuditLogResource::collection($mappedAuditLogs), 'info' => $info];
    }
}
