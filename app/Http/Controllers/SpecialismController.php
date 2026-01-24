<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SpecialismController extends Controller
{
    private const TYPES = [
        'kantoorruimte',
        'bedrijfsruimte',
        'logistiek',
        'winkelruimte',
        'recreatief_vastgoed',
        'maatschappelijk_vastgoed',
        'buitenterrein',
    ];

    private const PROVINCES = [
        'groningen',
        'friesland',
        'drenthe',
        'overijssel',
        'flevoland',
        'gelderland',
        'utrecht',
        'noord_holland',
        'zuid_holland',
        'zeeland',
        'noord_brabant',
        'limburg',
    ];

    public function edit(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Specialism/Edit', [
            'selection' => [
                'types' => $user->specialism_types ?? [],
                'provinces' => $user->specialism_provinces ?? [],
            ],
            'options' => [
                'types' => self::TYPES,
                'provinces' => self::PROVINCES,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'types' => ['array'],
            'types.*' => ['string', Rule::in(self::TYPES)],
            'provinces' => ['array'],
            'provinces.*' => ['string', Rule::in(self::PROVINCES)],
        ]);

        $user = $request->user();
        $user->specialism_types = $data['types'] ?? [];
        $user->specialism_provinces = $data['provinces'] ?? [];
        $user->save();

        return Redirect::route('search-requests.index')->with('status', 'specialism-updated');
    }
}
