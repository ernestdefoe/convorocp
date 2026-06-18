<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use App\Support\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BrandingController extends Controller
{
    private function ensureOperator(Request $request): void
    {
        abort_unless($request->user()->isOperator(), 403);
    }

    public function index(Request $request)
    {
        $this->ensureOperator($request);

        return Inertia::render('Branding/Index', [
            'name' => Setting::get('brand.name') ?: '',
            'accent' => Branding::accent(),
            'logo' => Setting::get('brand.logo') ?: null,
            'defaultName' => Branding::DEFAULT_NAME,
            'defaultAccent' => Branding::DEFAULT_ACCENT,
        ]);
    }

    public function save(Request $request)
    {
        $this->ensureOperator($request);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:40'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        Setting::set('brand.name', $data['name'] ?: null);
        Setting::set('brand.accent', $data['accent']);

        return back();
    }

    public function uploadLogo(Request $request)
    {
        $this->ensureOperator($request);
        // 200 KB cap — the logo rides along in shared props as a data URI.
        $request->validate([
            'logo' => ['required', 'file', 'max:200', 'mimes:png,jpg,jpeg,webp,svg'],
        ]);
        $file = $request->file('logo');
        $uri = 'data:'.$file->getMimeType().';base64,'.base64_encode(file_get_contents($file->getRealPath()));
        Setting::set('brand.logo', $uri);

        return back();
    }

    public function removeLogo(Request $request)
    {
        $this->ensureOperator($request);
        Setting::set('brand.logo', null);

        return back();
    }
}
