<?php

use App\Migrations\Support\ItalianGeoLocalizer;

beforeEach(function () {
    $this->localizer = new ItalianGeoLocalizer;
});

it('translates the country name and passes through unknown ones', function () {
    expect($this->localizer->country('Italia'))->toBe('Italy')
        ->and($this->localizer->country(' italia '))->toBe('Italy')
        ->and($this->localizer->country('France'))->toBe('France');
});

it('translates anglicized regions and the recurring Sicillia typo, passing native ones through', function () {
    expect($this->localizer->region('Sicilia'))->toBe('Sicily')
        ->and($this->localizer->region('Sicillia'))->toBe('Sicily')
        ->and($this->localizer->region('Lombardia'))->toBe('Lombardy')
        ->and($this->localizer->region('Puglia'))->toBe('Apulia')
        ->and($this->localizer->region('Emilia Romagna'))->toBe('Emilia-Romagna')
        ->and($this->localizer->region('Campania'))->toBe('Campania');
});

it('maps a province plate code to the reference name, null when unknown', function () {
    expect($this->localizer->province('NA'))->toBe('Naples')
        ->and($this->localizer->province('rg'))->toBe('Ragusa')
        ->and($this->localizer->province(' MI '))->toBe('Milan')
        ->and($this->localizer->province('MA'))->toBeNull()
        ->and($this->localizer->province('Po'))->toBe('Prato');
});

it('cleans the legacy comune label down to the bare city name', function () {
    expect($this->localizer->cleanCityLabel('FRATTAMAGGIORE 1 (HQ)'))->toBe('FRATTAMAGGIORE')
        ->and($this->localizer->cleanCityLabel('Roma - 2'))->toBe('Roma')
        ->and($this->localizer->cleanCityLabel('Benevento 1'))->toBe('Benevento')
        ->and($this->localizer->cleanCityLabel('Palermo (ex?)'))->toBe('Palermo')
        ->and($this->localizer->cleanCityLabel('Viterbo - Sede Temporanea'))->toBe('Viterbo');
});

it('translates anglicized cities after cleaning, null for a pure placeholder', function () {
    expect($this->localizer->city('Napoli'))->toBe('Naples')
        ->and($this->localizer->city('Roma - 2'))->toBe('Rome')
        ->and($this->localizer->city('Frattamaggiore'))->toBe('Frattamaggiore')
        ->and($this->localizer->city('(HQ)'))->toBeNull();
});
