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
}
