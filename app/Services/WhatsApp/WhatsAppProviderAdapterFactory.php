<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Adapters\EvolutionApiProvider;
use App\Services\WhatsApp\Adapters\GenericWhatsAppProvider;
use App\Services\WhatsApp\Adapters\WhatsAppCloudProvider;
use App\Services\WhatsApp\Contracts\WhatsAppProviderInterface;
use RuntimeException;

class WhatsAppProviderAdapterFactory
{
    public function __construct(private readonly SafeWhatsAppHttpClient $http) {}

    public function make(WhatsAppProvider $provider): WhatsAppProviderInterface
    {
        return match ($provider->provider_type) {
            'whatsapp_cloud'  => new WhatsAppCloudProvider($provider, $this->http),
            'evolution_api'   => new EvolutionApiProvider($provider, $this->http),
            'generic'         => new GenericWhatsAppProvider($provider, $this->http),
            default           => throw new RuntimeException("Unknown WhatsApp provider type: {$provider->provider_type}"),
        };
    }
}