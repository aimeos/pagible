<?php

namespace Aimeos\AnalyticsBridge;

use Illuminate\Support\Facades\Http;
use Google\Service\SearchConsole;
use Google\Client;


class Google
{
    private array $crux;
    private ?SearchConsole $console = null;


    public function __construct( array $config )
    {
        if(isset($config['auth']))
        {
            $client = new Client();
            $client->setAuthConfig($config['auth']);
            $client->addScope('https://www.googleapis.com/auth/webmasters.readonly');

            $this->console = new SearchConsole($client);
        }

        $this->crux = $config['crux'] ?? [];
    }


    public function pagespeed(string $url): ?array
    {
        if (!isset($this->crux['apikey'])) {
            return null;
        }

        if (!$url) {
            throw new \InvalidArgumentException('URL must be a non-empty string');
        }

        $payload = ['url' => $url];

        if (isset($this->crux['formFactor'])) {
            $payload['formFactor'] = $this->crux['formFactor'];
        }

        $endpoint = 'https://chromeuxreport.googleapis.com/v1/records:queryRecord';
        $response = Http::post($endpoint . '?key=' . $this->crux['apikey'], $payload);

        if ($response->failed()) {
            if ($response->body() === 'chrome ux report data not found') {
                throw new \RuntimeException($response->body());
            }
            return null;
        }

        $metrics = data_get($response->json(), 'record.metrics', []);

        return collect($metrics)
            ->map(fn($item, $key) => [
                'key' => !strncmp($key, 'experimental_', 13) ? substr($key, 13) : $key,
                'value' => $item['percentiles']['p75'] ?? null
            ])
            ->values()
            ->all();
    }


    public function indexed(string $url, string $lang = 'en'): ?string
    {
        if (!$this->console) {
            return null;
        }

        if (!$url) {
            throw new \InvalidArgumentException('URL must be a non-empty string');
        }

        $request = new SearchConsole\InspectUrlIndexRequest([
            'siteUrl' => $this->siteUrl($url),
            'inspectionUrl' => $url,
            'languageCode' => $lang,
        ]);


        $response = $this->console->urlInspection_index->inspect($request);
        return $response->getInspectionResult()->getIndexStatusResult()->getCoverageState();
    }


    public function search(string $url, int $days = 30): ?array
    {
        if (!$this->console) {
            return null;
        }

        if (!$url) {
            throw new \InvalidArgumentException('URL must be a non-empty string');
        }

        $request = new SearchConsole\SearchAnalyticsQueryRequest([
            'startDate' => now()->subDays($days)->toDateString(),
            'endDate' => now()->toDateString(),
            'dimensions' => ['date'],
            'dimensionFilterGroups' => [[
                'groupType' => 'and',
                'filters' => [
                    [
                        'dimension' => 'page',
                        'operator' => 'equals',
                        'expression' => $url,
                    ]
                ]
            ]],
        ]);

        $response = $this->console->searchanalytics->query($this->siteUrl($url), $request);
        $data = [];

        foreach ($response->getRows() as $row) {
            $key = $row->getKeys()[0];
            $data['impressions'][] = ['key' => $key, 'value' => $row->getImpressions()];
            $data['clicks'][] = ['key' => $key, 'value' => $row->getClicks()];
            $data['ctrs'][] = ['key' => $key, 'value' => $row->getCtr()];
        }

        return $data;
    }


    public function queries(string $url, int $days = 30): ?array
    {
        if (!$this->console) {
            return null;
        }

        if (!$url) {
            throw new \InvalidArgumentException('URL must be a non-empty string');
        }

        $request = new SearchConsole\SearchAnalyticsQueryRequest([
            'startDate' => now()->subDays($days)->toDateString(),
            'endDate' => now()->toDateString(),
            'dimensions' => ['query'], // Top queries
            'dimensionFilterGroups' => [[
                'groupType' => 'and',
                'filters' => [
                    [
                        'dimension' => 'page',
                        'operator' => 'equals',
                        'expression' => $url,
                    ]
                ]
            ]],
            'rowLimit' => 100
        ]);

        $response = $this->console->searchanalytics->query($this->siteUrl($url), $request);
        $data = [];

        foreach ($response->getRows() as $row) {
            $data[] = [
                'key' => $row->getKeys()[0],
                'impressions' => $row->getImpressions(),
                'clicks' => $row->getClicks(),
                'ctr' => $row->getCtr(),
                'position' => $row->getPosition()
            ];
        }

        return $data;
    }


    protected function siteUrl(string $url): string
    {
        $parts = parse_url($url);
        $siteUrl = $parts['scheme'] . '://' . $parts['host'];
        $siteUrl .= isset($parts['port']) ? ':' . $parts['port'] : '';
        $siteUrl .= '/';

        return $siteUrl;
    }
}
