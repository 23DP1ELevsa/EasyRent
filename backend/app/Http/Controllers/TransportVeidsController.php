<?php

namespace App\Http\Controllers;

use App\Models\TransportliedzieklsVeids;
use App\Support\AdminChanges;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransportVeidsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(TransportliedzieklsVeids::whereNull('deactivated_at')->orderBy('nosaukums')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean'], 'iemesls' => ['required', 'string', 'max:2000']]);
        $actor = $request->user();

        return AdminChanges::run($actor, function () use ($actor, $id, $data) {
            $category = TransportliedzieklsVeids::whereKey($id)->lockForUpdate()->firstOrFail();
            $before = $category->deactivated_at?->toISOString();
            $active = (bool) $data['active'];
            abort_if($active === ($before === null), 409, __('admin.state_unchanged'));
            $category->forceFill(['deactivated_at' => $active ? null : now()])->save();

            AdminChanges::record($actor, $active ? 'category_restored' : 'category_deactivated', 'category', $id, $data['iemesls'],
                ['deactivated_at' => $before], ['deactivated_at' => $category->deactivated_at?->toISOString()]);

            return response()->json($category);
        });
    }

    private function save(Request $request, ?int $id = null): JsonResponse
    {
        $actor = $request->user();

        try {
            return AdminChanges::run($actor, function () use ($request, $actor, $id) {
                $category = $id === null ? new TransportliedzieklsVeids : TransportliedzieklsVeids::whereKey($id)->lockForUpdate()->firstOrFail();
                $unique = Rule::unique('transportlidzekla_veids', 'nosaukums');
                $data = $request->validate([
                    'nosaukums' => ['required', 'string', 'max:50', $id === null ? $unique : $unique->ignore($id, 'veids_id')],
                    'tips' => ['nullable', 'string', 'max:50'],
                    'iemesls' => ['required', 'string', 'max:2000'],
                ]);
                $before = $id === null ? null : $category->only(['nosaukums', 'tips']);
                $category->fill([
                    'nosaukums' => $data['nosaukums'],
                    'tips' => Str::limit(Str::slug($data['tips'] ?? $data['nosaukums'], '_') ?: 'transporta_veids', 50, ''),
                ])->save();

                AdminChanges::record($actor, $id === null ? 'category_created' : 'category_updated', 'category',
                    $category->getKey(), $data['iemesls'], $before, $category->only(['nosaukums', 'tips']));

                return response()->json($category, $id === null ? 201 : 200);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nosaukums' => __('validation.unique', ['attribute' => __('admin.category_name')])]);
        }
    }
}
