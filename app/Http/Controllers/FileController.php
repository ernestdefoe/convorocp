<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Web file manager, scoped to a single site's directory. Site files are
 * www-data-owned (the agent chowns them), so the panel reads/writes them
 * directly. Every path is confined to the site base — traversal is rejected.
 */
class FileController extends Controller
{
    private function authorizeSite(Request $request, Site $site): void
    {
        abort_unless($request->user()->isOperator() || $site->user_id === $request->user()->id, 403);
    }

    private function base(Site $site): string
    {
        $b = realpath("/var/www/sites/{$site->domain}");
        abort_unless($b !== false, 404, 'Site files not found on this node.');

        return $b;
    }

    /** Resolve a relative path under the base, or abort on escape. $mustExist=false for writes. */
    private function safe(string $base, string $rel, bool $mustExist = true): string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        abort_if(str_contains($rel, '..'), 403, 'Bad path.');
        $full = $rel === '' ? $base : "{$base}/{$rel}";

        if ($mustExist) {
            $real = realpath($full);
            abort_unless($real !== false && ($real === $base || str_starts_with($real, $base.'/')), 404);

            return $real;
        }
        $dir = realpath(dirname($full));
        abort_unless($dir !== false && ($dir === $base || str_starts_with($dir, $base.'/')), 403);

        return $dir.'/'.basename($full);
    }

    public function index(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);

        $base = realpath("/var/www/sites/{$site->domain}");
        if ($base === false) {
            return Inertia::render('Files/Index', [
                'site' => ['id' => $site->id, 'domain' => $site->domain],
                'path' => '', 'parent' => null, 'entries' => [], 'file' => null, 'provisioned' => false,
            ]);
        }

        $rel = (string) $request->query('path', '');
        $real = $this->safe($base, $rel);

        $common = ['site' => ['id' => $site->id, 'domain' => $site->domain], 'path' => trim($rel, '/'), 'parent' => $this->parent($rel), 'provisioned' => true];

        if (is_file($real)) {
            $size = filesize($real);
            $editable = $size <= 524288;

            return Inertia::render('Files/Index', $common + [
                'entries' => null,
                'file' => [
                    'name' => basename($real),
                    'content' => $editable ? file_get_contents($real) : null,
                    'editable' => $editable,
                    'size' => $size,
                ],
            ]);
        }

        $entries = collect(scandir($real) ?: [])
            ->reject(fn ($n) => $n === '.' || $n === '..')
            ->map(function ($n) use ($real, $rel) {
                $p = "{$real}/{$n}";
                $isDir = is_dir($p);

                return [
                    'name' => $n,
                    'type' => $isDir ? 'dir' : 'file',
                    'size' => $isDir ? null : @filesize($p),
                    'path' => ltrim(trim($rel, '/').'/'.$n, '/'),
                ];
            })
            ->sortBy(fn ($e) => ($e['type'] === 'dir' ? '0' : '1').strtolower($e['name']))
            ->values();

        return Inertia::render('Files/Index', $common + ['entries' => $entries, 'file' => null]);
    }

    public function save(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate(['path' => ['required', 'string'], 'content' => ['nullable', 'string']]);
        $target = $this->safe($this->base($site), $data['path'], false);
        file_put_contents($target, $data['content'] ?? '');

        return back();
    }

    public function upload(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate(['path' => ['nullable', 'string'], 'file' => ['required', 'file', 'max:51200']]);
        $base = $this->base($site);
        $dir = $this->safe($base, (string) ($data['path'] ?? ''));
        abort_unless(is_dir($dir), 422);
        $request->file('file')->move($dir, $request->file('file')->getClientOriginalName());

        return back();
    }

    public function mkdir(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate([
            'path' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120', 'regex:/^[\w.\- ]+$/'],
        ]);
        $dir = $this->safe($this->base($site), trim(($data['path'] ?? '').'/'.$data['name'], '/'), false);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return back();
    }

    public function destroy(Request $request, Site $site)
    {
        $this->authorizeSite($request, $site);
        $data = $request->validate(['path' => ['required', 'string']]);
        $target = $this->safe($this->base($site), $data['path']);
        if (is_dir($target)) {
            @rmdir($target);
        } else {
            @unlink($target);
        }

        return back();
    }

    private function parent(string $rel): ?string
    {
        $rel = trim($rel, '/');
        if ($rel === '') {
            return null;
        }
        $p = trim(dirname($rel), '/');

        return $p === '.' ? '' : $p;
    }
}
