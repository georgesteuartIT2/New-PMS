<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Illuminate\Support\Str;

class AzureGraphService {
    public function configured(): bool {
        return filled(config('services.azure.client_id'))
            && filled(config('services.azure.client_secret'))
            && filled(config('services.azure.tenant'));
    }

    public function token(): string {
        if (!$this->configured()) {
            throw new RuntimeException('Microsoft Entra configuration is missing in .env');
        }
        $id = config('services.azure.client_id');
        $tenant = config('services.azure.tenant');

        return Cache::remember('azure-graph-token-' . sha1($id . $tenant), now()->addMinutes(45), function () use ($tenant) {
            $r = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/' . rawurlencode($tenant) . '/oauth2/v2.0/token', [
                'client_id' => config('services.azure.client_id'),
                'client_secret' => config('services.azure.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ]);
            $r->throw();
            return $r->json('access_token');
        });
    }

    /**
     * Search directory users by display name, email, or principal name.
     * Returns an empty array if query has fewer than 2 characters (no initial auto-listing).
     */
    public function search(string $query = ''): array {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $safe = str_replace(['"', "'", '\\'], '', $query);

        // 1. Try Microsoft Graph $search with ConsistencyLevel: eventual (official full-text search)
        try {
            $r = Http::withToken($this->token())
                ->withHeaders(['ConsistencyLevel' => 'eventual'])
                ->timeout(12)
                ->get('https://graph.microsoft.com/v1.0/users', [
                    '$search' => "\"displayName:{$safe}\" OR \"mail:{$safe}\" OR \"userPrincipalName:{$safe}\"",
                    '$select' => 'id,displayName,mail,userPrincipalName,accountEnabled,companyName,department,jobTitle',
                    '$top' => 25,
                ]);

            if ($r->successful()) {
                $items = $r->json('value', []);
                if (!empty($items)) {
                    return $items;
                }
            }
        } catch (\Throwable $e) {
            // fallback to startsWith
        }

        // 2. Fallback to startswith filter across display name, email, principal name, surname, givenName
        try {
            $filter = "startswith(displayName,'{$safe}') or startswith(mail,'{$safe}') or startswith(userPrincipalName,'{$safe}') or startswith(surname,'{$safe}') or startswith(givenName,'{$safe}')";
            $r = Http::withToken($this->token())
                ->timeout(12)
                ->get('https://graph.microsoft.com/v1.0/users', [
                    '$filter' => $filter,
                    '$select' => 'id,displayName,mail,userPrincipalName,accountEnabled,companyName,department,jobTitle',
                    '$top' => 25,
                ]);

            if ($r->successful()) {
                return $r->json('value', []);
            }
        } catch (\Throwable $e) {
            // log error
        }

        return [];
    }

    /**
     * Get recent/all directory users without filtering.
     */
    public function allUsers(int $top = 50): array {
        $r = Http::withToken($this->token())->timeout(15)->get('https://graph.microsoft.com/v1.0/users', [
            '$select' => 'id,displayName,mail,userPrincipalName,accountEnabled,companyName,department,jobTitle',
            '$top' => $top,
        ]);
        $r->throw();
        return $r->json('value', []);
    }

    /**
     * Inspect Azure AD directory to extract unique Company/Subsidiary names.
     */
    public function getSubsidiariesFromDirectory(): array {
        $users = Http::withToken($this->token())->timeout(20)->get('https://graph.microsoft.com/v1.0/users', [
            '$select' => 'companyName,department,mail,userPrincipalName',
            '$top' => 999,
        ])->json('value', []);

        $domainMap = [
            'georgesteuart.lk' => ['name' => 'George Steuart & Co', 'code' => 'GS'],
            'gstravels.lk' => ['name' => 'GS Travels', 'code' => 'GST'],
            'steuartholidays.com' => ['name' => 'Steuart Holidays', 'code' => 'GSHL'],
            'gsconsumer.lk' => ['name' => 'GS Consumer Goods', 'code' => 'GSC'],
            'gshealth.lk' => ['name' => 'GS Health', 'code' => 'GSH'],
            'gssolutions.lk' => ['name' => 'GS Solutions', 'code' => 'GSS'],
            'gsteas.lk' => ['name' => 'GS Teas', 'code' => 'GSTE'],
            'heladiv.com' => ['name' => 'Heladiv Tea', 'code' => 'HT'],
            'heladiv.lk' => ['name' => 'Heladiv', 'code' => 'HLD'],
            'gsinsurance.lk' => ['name' => 'GS Insurance', 'code' => 'GSI'],
            'gsoptimize.lk' => ['name' => 'GS Optimize', 'code' => 'GSO'],
        ];

        $companies = [];
        foreach ($users as $u) {
            $comp = trim($u['companyName'] ?? '');
            if (!empty($comp) && !isset($companies[$comp])) {
                $companies[$comp] = [
                    'name' => $comp,
                    'code' => $this->suggestCode($comp),
                    'source' => 'Azure Company'
                ];
            }
            $dept = trim($u['department'] ?? '');
            if (!empty($dept) && !isset($companies[$dept])) {
                $companies[$dept] = [
                    'name' => $dept,
                    'code' => $this->suggestCode($dept),
                    'source' => 'Azure Department'
                ];
            }

            // Also check email domain
            $email = strtolower(trim($u['mail'] ?: ($u['userPrincipalName'] ?? '')));
            if ($email && str_contains($email, '@')) {
                $domain = explode('@', $email)[1];
                if (isset($domainMap[$domain])) {
                    $item = $domainMap[$domain];
                    if (!isset($companies[$item['name']])) {
                        $companies[$item['name']] = [
                            'name' => $item['name'],
                            'code' => $item['code'],
                            'source' => 'Azure Domain (' . $domain . ')'
                        ];
                    }
                }
            }
        }

        return array_values($companies);
    }

    private function suggestCode(string $name): string {
        $words = preg_split('/[\s\-_]+/', trim($name));
        if (count($words) >= 2) {
            $code = '';
            foreach ($words as $w) {
                if (!empty($w)) $code .= strtoupper($w[0]);
            }
            if (strlen($code) >= 2) return substr($code, 0, 4);
        }
        $cleaned = preg_replace('/[^A-Za-z0-9]/', '', $name);
        return strtoupper(substr($cleaned, 0, 3));
    }

    public function get(string $id): array {
        if (!preg_match('/^[a-f0-9-]{36}$/i', $id)) {
            throw new RuntimeException('Invalid Microsoft user ID.');
        }
        $r = Http::withToken($this->token())->timeout(15)->get('https://graph.microsoft.com/v1.0/users/' . $id, [
            '$select' => 'id,displayName,mail,userPrincipalName,accountEnabled,companyName,department,jobTitle',
        ]);
        $r->throw();
        return $r->json();
    }

    /**
     * Get free/busy schedule for a list of attendee emails from Microsoft Graph Calendar.
     * Gracefully falls back to local scheduled meetings if Microsoft Graph is unconfigured or fails.
     */
    public function getSchedule(array $userEmails, string $startDate, ?string $timeZone = null): array {
        $timeZone = $timeZone ?: config('app.timezone', 'Asia/Colombo');
        $userEmails = array_values(array_unique(array_filter(array_map('trim', $userEmails))));

        if (empty($userEmails)) {
            return [];
        }

        $startIso = \Carbon\Carbon::parse($startDate)->startOfDay()->format('Y-m-d\T00:00:00');
        $endIso = \Carbon\Carbon::parse($startDate)->endOfDay()->format('Y-m-d\T23:59:59');

        $result = [];
        $graphSuccess = false;

        if ($this->configured()) {
            try {
                $token = $this->token();
                $primaryEmail = $userEmails[0];
                $response = Http::withToken($token)
                    ->timeout(12)
                    ->post("https://graph.microsoft.com/v1.0/users/" . rawurlencode($primaryEmail) . "/calendar/getSchedule", [
                        'schedules' => $userEmails,
                        'startTime' => [
                            'dateTime' => $startIso,
                            'timeZone' => $timeZone,
                        ],
                        'endTime' => [
                            'dateTime' => $endIso,
                            'timeZone' => $timeZone,
                        ],
                        'availabilityViewInterval' => 30,
                    ]);

                if ($response->successful()) {
                    $items = $response->json('value', []);
                    foreach ($items as $item) {
                        $email = strtolower($item['scheduleId'] ?? '');
                        $busySlots = [];
                        foreach ($item['scheduleItems'] ?? [] as $si) {
                            $busySlots[] = [
                                'subject' => $si['subject'] ?? 'Busy (Outlook)',
                                'status' => strtolower($si['status'] ?? 'busy'),
                                'start' => $si['start']['dateTime'] ?? '',
                                'end' => $si['end']['dateTime'] ?? '',
                                'source' => 'outlook',
                            ];
                        }
                        $result[$email] = [
                            'email' => $email,
                            'availability_view' => $item['availabilityView'] ?? '',
                            'busy_slots' => $busySlots,
                            'source' => 'microsoft_graph',
                        ];
                    }
                    $graphSuccess = true;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Microsoft Graph getSchedule error: ' . $e->getMessage());
            }
        }

        // Fill or merge with local database meetings so all meetings are respected
        $dateStart = \Carbon\Carbon::parse($startDate)->startOfDay();
        $dateEnd = \Carbon\Carbon::parse($startDate)->endOfDay();

        $localMeetings = \App\Models\Meeting::with(['attendeeRecords', 'creator'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($dateStart, $dateEnd) {
                $q->whereBetween('starts_at', [$dateStart, $dateEnd])
                  ->orWhereBetween('ends_at', [$dateStart, $dateEnd])
                  ->orWhere(function ($sub) use ($dateStart, $dateEnd) {
                      $sub->where('starts_at', '<=', $dateStart)->where('ends_at', '>=', $dateEnd);
                  });
            })
            ->get();

        foreach ($userEmails as $rawEmail) {
            $email = strtolower($rawEmail);
            if (!isset($result[$email])) {
                $result[$email] = [
                    'email' => $email,
                    'availability_view' => '',
                    'busy_slots' => [],
                    'source' => $graphSuccess ? 'microsoft_graph' : 'local_database',
                ];
            }

            foreach ($localMeetings as $m) {
                $isAttending = strtolower($m->creator?->email ?? '') === $email ||
                               $m->attendeeRecords->contains(fn ($ar) => strtolower($ar->email) === $email);

                if ($isAttending) {
                    $result[$email]['busy_slots'][] = [
                        'subject' => $m->title,
                        'status' => 'busy',
                        'start' => $m->starts_at->format('Y-m-d\TH:i:s'),
                        'end' => $m->ends_at->format('Y-m-d\TH:i:s'),
                        'meeting_id' => $m->id,
                        'source' => 'projectflow',
                    ];
                }
            }

            usort($result[$email]['busy_slots'], function ($a, $b) {
                return strcmp($a['start'], $b['start']);
            });
        }

        return $result;
    }

    /**
     * Create an event in Microsoft Outlook Calendar via Microsoft Graph API.
     */
    public function createEvent(\App\Models\Meeting $meeting): array {
        if (!$this->configured()) {
            return [
                'success' => false,
                'status' => 'local_only',
                'error' => 'Microsoft Azure configuration is not enabled in .env.',
            ];
        }

        try {
            $token = $this->token();
            $organizer = $meeting->creator;
            $organizerEmail = $organizer?->email ?: config('mail.from.address');

            $attendees = [];
            foreach ($meeting->attendeeRecords as $att) {
                $attendees[] = [
                    'emailAddress' => [
                        'address' => $att->email,
                        'name' => $att->name,
                    ],
                    'type' => 'required',
                ];
            }

            $timeZone = config('app.timezone', 'Asia/Colombo');
            $payload = [
                'subject' => $meeting->title,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => nl2br(e($meeting->description ?: 'Meeting scheduled via GS ProjectFlow.')) .
                                 "<br><br><strong>Project:</strong> " . e($meeting->project?->name ?? 'General') .
                                 "<br><strong>Organized by:</strong> " . e($organizer?->name ?? 'PMO'),
                ],
                'start' => [
                    'dateTime' => $meeting->starts_at->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timeZone,
                ],
                'end' => [
                    'dateTime' => $meeting->ends_at->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timeZone,
                ],
                'location' => [
                    'displayName' => $meeting->location ?: 'Online Meeting',
                ],
                'attendees' => $attendees,
                'isOnlineMeeting' => true,
                'onlineMeetingProvider' => 'teamsForBusiness',
            ];

            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.microsoft.com/v1.0/users/" . rawurlencode($organizerEmail) . "/events", $payload);

            if ($response->successful()) {
                $data = $response->json();
                $eventId = $data['id'] ?? null;
                $joinUrl = $data['onlineMeeting']['joinUrl'] ?? ($data['webLink'] ?? null);

                return [
                    'success' => true,
                    'status' => 'synced',
                    'ms_event_id' => $eventId,
                    'meeting_link' => $joinUrl,
                    'web_link' => $data['webLink'] ?? null,
                ];
            }

            $errMsg = $response->json('error.message', 'HTTP ' . $response->status());
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $errMsg,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Microsoft Graph createEvent failed: ' . $e->getMessage());
            return [
                'success' => false,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update an event in Microsoft Outlook Calendar via Microsoft Graph API.
     */
    public function updateEvent(\App\Models\Meeting $meeting): array {
        if (!$this->configured() || empty($meeting->ms_event_id)) {
            return ['success' => false, 'status' => 'local_only'];
        }

        try {
            $token = $this->token();
            $organizerEmail = $meeting->creator?->email ?: config('mail.from.address');
            $timeZone = config('app.timezone', 'Asia/Colombo');

            $attendees = [];
            foreach ($meeting->attendeeRecords as $att) {
                $attendees[] = [
                    'emailAddress' => [
                        'address' => $att->email,
                        'name' => $att->name,
                    ],
                    'type' => 'required',
                ];
            }

            $payload = [
                'subject' => $meeting->title,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => nl2br(e($meeting->description ?: '')) .
                                 "<br><br><strong>Project:</strong> " . e($meeting->project?->name ?? 'General'),
                ],
                'start' => [
                    'dateTime' => $meeting->starts_at->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timeZone,
                ],
                'end' => [
                    'dateTime' => $meeting->ends_at->format('Y-m-d\TH:i:s'),
                    'timeZone' => $timeZone,
                ],
                'location' => [
                    'displayName' => $meeting->location ?: 'Online Meeting',
                ],
                'attendees' => $attendees,
            ];

            $response = Http::withToken($token)
                ->timeout(15)
                ->patch("https://graph.microsoft.com/v1.0/users/" . rawurlencode($organizerEmail) . "/events/" . $meeting->ms_event_id, $payload);

            return ['success' => $response->successful(), 'status' => $response->successful() ? 'synced' : 'failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Delete an event in Microsoft Outlook Calendar via Microsoft Graph API.
     */
    public function deleteEvent(\App\Models\Meeting $meeting): bool {
        if (!$this->configured() || empty($meeting->ms_event_id)) {
            return true;
        }

        try {
            $token = $this->token();
            $organizerEmail = $meeting->creator?->email ?: config('mail.from.address');

            $response = Http::withToken($token)
                ->timeout(12)
                ->delete("https://graph.microsoft.com/v1.0/users/" . rawurlencode($organizerEmail) . "/events/" . $meeting->ms_event_id);

            return $response->successful() || $response->status() === 404;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Microsoft Graph deleteEvent failed: ' . $e->getMessage());
            return false;
        }
    }
}
