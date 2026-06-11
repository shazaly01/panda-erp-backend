<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BrandingResource;

class BrandingController extends Controller
{
    /**
     * Get the system branding configuration.
     *
     * @return BrandingResource
     */
    public function index(): BrandingResource
    {
        $brandingData = [
            'app_name' => config('branding.app_name'),
            'logo_full_url' => asset(config('branding.logo_full')),
            'logo_mini_url' => asset(config('branding.logo_mini')),
            'favicon_url' => asset(config('branding.favicon')),
        ];

        return BrandingResource::make($brandingData);
    }
}
