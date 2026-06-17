<?php

namespace App\Http\Controllers;

use App\Models\Database;
use App\Support\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DatabaseController extends Controller
{
    private function scoped(Request $request)
    {
        return $request->user()->isOperator()
            ? Database::query()
            : Database::where('user_id', $request->user()->id);
    }

    public function index(Request $request)
    {
        $databases = $this->scoped($request)->with('owner')->latest()->get()->map(fn (Database $d) => [
            'id' => $d->id,
            'name' => $d->name,
            'engine' => $d->engine,
            'db_user' => $d->db_user,
            'password' => $d->db_password,
            'owner' => $d->owner?->name,
        ]);

        return Inertia::render('Databases/Index', [
            'databases' => $databases,
            'engines' => config('convorocp.db_engines'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64', 'unique:databases,name', 'regex:/^[a-z0-9_]+$/i'],
            'engine' => ['required', 'in:'.implode(',', array_keys(config('convorocp.db_engines')))],
        ]);

        $dbUser = Str::limit($data['name'], 24, '').'_user';
        $password = Str::random(20);
        Database::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'engine' => $data['engine'],
            'db_user' => $dbUser,
            'db_password' => $password,
        ]);

        Agent::dispatch('db.create', ['name' => $data['name'], 'engine' => $data['engine']]);
        Agent::dispatch('db.user.create', ['user' => $dbUser, 'engine' => $data['engine'], 'grant' => $data['name'], 'password' => $password]);

        return redirect('/databases');
    }

    public function destroy(Request $request, Database $database)
    {
        abort_unless($request->user()->isOperator() || $database->user_id === $request->user()->id, 403);
        Agent::dispatch('db.drop', ['name' => $database->name, 'engine' => $database->engine, 'user' => $database->db_user]);
        $database->delete();

        return redirect('/databases');
    }
}
