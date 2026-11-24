<?php

namespace App\Http\Controllers;

use App\Crud\Resources;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CrudController extends Controller
{
    private function resource(string $slug): array
    {
        $r = Resources::get($slug);
        abort_unless($r, 404);
        abort_unless(auth()->user()->hasRole(...$r['roles']), 403);

        return $r;
    }

    public function index(Request $request, string $resource)
    {
        $r = $this->resource($resource);
        $q = $r['model']::query()->with($r['with'] ?? []);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(function ($w) use ($r, $s) {
                foreach ($r['search'] ?? [] as $col) {
                    $w->orWhere($col, 'like', "%$s%");
                }
            });
        }
        foreach ($r['filters'] ?? [] as $col => $f) {
            if ($request->filled($col)) {
                $q->where($col, $request->query($col));
            }
        }
        [$col, $dir] = $r['order'] ?? ['id', 'desc'];
        $rows = $q->orderBy($col, $dir)->paginate(25)->withQueryString();

        return view('crud.index', ['r' => $r, 'slug' => $resource, 'rows' => $rows]);
    }

    public function create(string $resource)
    {
        return view('crud.form', ['r' => $this->resource($resource), 'slug' => $resource, 'model' => null]);
    }

    public function edit(string $resource, int $id)
    {
        $r = $this->resource($resource);

        return view('crud.form', ['r' => $r, 'slug' => $resource, 'model' => $r['model']::findOrFail($id)]);
    }

    public function store(Request $request, string $resource)
    {
        return $this->save($request, $resource, null);
    }

    public function update(Request $request, string $resource, int $id)
    {
        $r = $this->resource($resource);

        return $this->save($request, $resource, $r['model']::findOrFail($id));
    }

    private function save(Request $request, string $slug, $model)
    {
        $r = $this->resource($slug);
        $rules = [];
        $r['fields'] = array_values(array_filter($r['fields'], fn ($f) => ! ($model && ($f['locked'] ?? false))));
        foreach ($r['fields'] as $f) {
            $rule = $f['rules'] ?? 'nullable';
            $rules[$f['name']] = is_callable($rule) && ! is_string($rule) ? $rule($model) : $rule;
        }
        $data = $request->validate($rules);
        foreach ($r['fields'] as $f) {
            if (($f['type'] ?? '') === 'checkbox') {
                $data[$f['name']] = $request->boolean($f['name']);
            }
        }
        if (isset($r['validate']) && ($errs = $r['validate']($data))) {
            throw ValidationException::withMessages($errs);
        }
        if (isset($r['saving'])) {
            $data = $r['saving']($data, $model);
        }
        $model ??= new $r['model'];
        $model->fill(Arr::only($data, array_keys($data)));
        $model->save();
        $extra = isset($r['after_save']) ? $r['after_save']($model) : null;

        return redirect()->route('crud.index', $slug)->with('ok', ucfirst($r['singular']).' saved.'.(is_string($extra) ? ' '.$extra : ''));
    }

    public function destroy(string $resource, int $id)
    {
        $r = $this->resource($resource);
        $m = $r['model']::findOrFail($id);
        if (isset($r['can_delete']) && ! $r['can_delete']($m)) {
            return back()->with('error', 'This record cannot be deleted.');
        }
        try {
            $m->delete();
        } catch (QueryException) {
            return back()->with('error', 'This '.$r['singular'].' is still in use by other records, so it cannot be deleted.');
        }

        return redirect()->route('crud.index', $resource)->with('ok', ucfirst($r['singular']).' deleted.');
    }
}
