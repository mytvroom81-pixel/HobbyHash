<?php
declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use GuzzleHttp\Client as HttpClient;

/**
 * Google Cloud Translation client wrapper (server-side only).
 *
 * Primary: Cloud Translation API v3 translateText with service-account credentials.
 * Fallback: official REST v2 translate endpoint when only an API key is configured.
 */
final class HobcGoogleTranslateClient
{
    private string $projectId;
    private string $location;
    /** @var 'service_account'|'api_key' */
    private string $authMode;
    private ?string $credentialsPath = null;
    private ?string $apiKey = null;
    private ?TranslationServiceClient $v3Client = null;

    public function __construct()
    {
        $this->projectId = trim((string)getenv('GOOGLE_CLOUD_PROJECT_ID'));
        $this->location = trim((string)(getenv('GOOGLE_TRANSLATE_LOCATION') ?: 'global'));
        if ($this->location === '') {
            $this->location = 'global';
        }

        $credentialsEnv = trim((string)getenv('GOOGLE_APPLICATION_CREDENTIALS'));
        $apiKeyEnv = trim((string)getenv('GOOGLE_CLOUD_API_KEY'));

        if ($credentialsEnv !== '' && is_file($credentialsEnv)) {
            $this->authMode = 'service_account';
            $this->credentialsPath = $credentialsEnv;
            return;
        }

        if ($apiKeyEnv !== '') {
            $this->authMode = 'api_key';
            $this->apiKey = $apiKeyEnv;
            return;
        }

        if ($credentialsEnv !== '' && str_starts_with($credentialsEnv, 'AIza')) {
            $this->authMode = 'api_key';
            $this->apiKey = $credentialsEnv;
            return;
        }

        throw new RuntimeException(
            'Google Translation credentials are missing. Set GOOGLE_CLOUD_PROJECT_ID and either '
            . 'GOOGLE_APPLICATION_CREDENTIALS to a service-account JSON file path, or GOOGLE_CLOUD_API_KEY '
            . '(or an API key mistakenly placed in GOOGLE_APPLICATION_CREDENTIALS).'
        );
    }

    public function authMode(): string
    {
        return $this->authMode;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function location(): string
    {
        return $this->location;
    }

    public static function validateConfiguration(): void
    {
        $client = new self();
        if ($client->authMode === 'service_account' && $client->projectId === '') {
            throw new RuntimeException('GOOGLE_CLOUD_PROJECT_ID is required for Cloud Translation API v3.');
        }
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     */
    public function translateBatch(array $texts, string $targetLocale, string $sourceLocale = 'en'): array
    {
        $texts = array_values(array_map(static fn($v): string => (string)$v, $texts));
        if ($texts === []) {
            return [];
        }

        $googleTarget = self::mapLocaleToGoogle($targetLocale);
        $googleSource = self::mapLocaleToGoogle($sourceLocale);

        if ($this->authMode === 'service_account') {
            return $this->translateBatchV3($texts, $googleTarget, $googleSource);
        }

        return $this->translateBatchV2($texts, $googleTarget, $googleSource);
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     */
    private function translateBatchV3(array $texts, string $targetLocale, string $sourceLocale): array
    {
        if ($this->projectId === '') {
            throw new RuntimeException('GOOGLE_CLOUD_PROJECT_ID is required for translateText (v3).');
        }

        $client = $this->v3Client ??= new TranslationServiceClient([
            'credentials' => $this->credentialsPath,
        ]);

        $parent = TranslationServiceClient::locationName($this->projectId, $this->location);
        $request = (new TranslateTextRequest())
            ->setParent($parent)
            ->setContents($texts)
            ->setMimeType('text/plain')
            ->setSourceLanguageCode($sourceLocale)
            ->setTargetLanguageCode($targetLocale);

        $response = $client->translateText($request);
        $out = [];
        foreach ($response->getTranslations() as $translation) {
            $out[] = (string)$translation->getTranslatedText();
        }

        if (count($out) !== count($texts)) {
            throw new RuntimeException('Google translateText returned an unexpected number of translations.');
        }

        return $out;
    }

    /**
     * @param list<string> $texts
     * @return list<string>
     */
    private function translateBatchV2(array $texts, string $targetLocale, string $sourceLocale): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('GOOGLE_CLOUD_API_KEY is required for REST v2 translation fallback.');
        }

        $http = new HttpClient(['timeout' => 60]);
        $url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($this->apiKey);
        $response = $http->post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'q' => $texts,
                'source' => $sourceLocale,
                'target' => $targetLocale,
                'format' => 'text',
            ],
        ]);

        $body = json_decode((string)$response->getBody(), true);
        if (!is_array($body) || !isset($body['data']['translations']) || !is_array($body['data']['translations'])) {
            throw new RuntimeException('Google REST v2 translation returned an invalid response.');
        }

        $out = [];
        foreach ($body['data']['translations'] as $row) {
            $out[] = html_entity_decode((string)($row['translatedText'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (count($out) !== count($texts)) {
            throw new RuntimeException('Google REST v2 translation returned an unexpected number of translations.');
        }

        return $out;
    }

    public static function mapLocaleToGoogle(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));
        $map = [
            'pt-BR' => 'pt-BR',
            'zh-CN' => 'zh-CN',
            'zh-TW' => 'zh-TW',
            'tl' => 'fil',
        ];
        return $map[$locale] ?? $locale;
    }
}
