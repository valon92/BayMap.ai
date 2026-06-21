<?php

namespace App\Support;

/**
 * Albanian auto-parts vocabulary → canonical component keys (longest phrase wins).
 */
class AutomotivePartsAlbanianLexicon
{
    /** @var array<string, string> phrase (lowercase) => component key */
    private const PHRASES = [
        'sensori i presionit të gomave' => 'tpms_sensor',
        'sensori i presionit te gomave' => 'tpms_sensor',
        'kapakët dekorativë të motorit' => 'engine_cover',
        'kapaket dekorative te motorit' => 'engine_cover',
        'lidhëset e stabilizatorit' => 'sway_bar_link',
        'lidhëset e stabilizator' => 'sway_bar_link',
        'ventilatori i radiatorit' => 'radiator_fan',
        'rezervuari i ftohësit' => 'coolant_reservoir',
        'rezervuari i ftohesit' => 'coolant_reservoir',
        'rezervuari i larësit të xhamave' => 'washer_reservoir',
        'rezervuari i laresit te xhamave' => 'washer_reservoir',
        'grykat e larjes së xhamave' => 'washer_nozzle',
        'grykat e larjes se xhamave' => 'washer_nozzle',
        'shkopi matës i vajit' => 'dipstick',
        'shkopi mates i vajit' => 'dipstick',
        'sistemi start-stop' => 'start_stop_system',
        'sustat e valvulave' => 'valve_spring',
        'unazat e pistonit' => 'piston_ring',
        'shufra lidhëse' => 'connecting_rod',
        'shufra lidhuese' => 'connecting_rod',
        'boshti i krankut' => 'crankshaft',
        'boshti me gunga' => 'camshaft',
        'kolektori i marrjes' => 'intake_manifold',
        'kolektori i shkarkimit' => 'exhaust_manifold',
        'filtri i karburantit' => 'fuel_filter',
        'filtri i karburant' => 'fuel_filter',
        'pompa e karburantit' => 'fuel_pump',
        'rezervuari i karburantit' => 'fuel_tank',
        'pompa e vajit' => 'oil_pump',
        'filtri i vajit' => 'oil_filter',
        'filtri i ajrit' => 'air_filter',
        'filtri i kabinës' => 'cabin_filter',
        'filtri i kabines' => 'cabin_filter',
        'filtri dpf' => 'dpf_filter',
        'kompresori i klimës' => 'ac_compressor',
        'kompresori i klim' => 'ac_compressor',
        'kutia e shpejtësive' => 'gearbox',
        'kutia e shpejtesive' => 'gearbox',
        'boshti i transmisionit' => 'driveshaft',
        'kardan boshti' => 'propshaft',
        'disqet e frenave' => 'brake_disc',
        'pllakat e frenave' => 'brake_pad',
        'pompa e frenave' => 'brake_master_cylinder',
        'tubat e frenave' => 'brake_line',
        'freni i dorës' => 'handbrake',
        'freni i dores' => 'handbrake',
        'kolona e timonit' => 'steering_column',
        'kokat e timonit' => 'tie_rod_end',
        'pompa servo' => 'power_steering_pump',
        'krahu i poshtëm' => 'control_arm',
        'krahu i poshtem' => 'control_arm',
        'krahu i sipërm' => 'control_arm',
        'krahu i siperm' => 'control_arm',
        'hub-i i rrotës' => 'wheel_hub',
        'hub i rrotes' => 'wheel_hub',
        'bulonat e rrotave' => 'wheel_bolt',
        'kapaku i motorit' => 'valve_cover',
        'parakolpi i përparmë' => 'front_bumper',
        'parakolpi i perparme' => 'front_bumper',
        'parakolpi i pasmë' => 'rear_bumper',
        'parakolpi i pasme' => 'rear_bumper',
        'grila e përparme' => 'grille',
        'grila e perparme' => 'grille',
        'menteshat e dyerve' => 'door_hinge',
        'dorezat e dyerve' => 'door_handle',
        'pasqyrat anësore' => 'mirror',
        'pasqyrat anesore' => 'mirror',
        'xhami i përparmë' => 'windshield',
        'xhami i perparme' => 'windshield',
        'xhami i pasmë' => 'rear_glass',
        'xhami i pasme' => 'rear_glass',
        'xhamat anësorë' => 'side_window',
        'xhamat anesore' => 'side_window',
        'mekanizmi i xhamave' => 'window_regulator',
        'fshirëset e xhamit' => 'wiper',
        'fshirëset e xham' => 'wiper',
        'motori i fshirëseve' => 'wiper_motor',
        'motori i fshirëse' => 'wiper_motor',
        'dritat e përparme' => 'headlight',
        'dritat e perparme' => 'headlight',
        'dritat e pasme' => 'tail_light',
        'dritat e mjegullës' => 'fog_light',
        'dritat e mjegull' => 'fog_light',
        'kapaku i bagazhit' => 'trunk_lid',
        'ulëset e përparme' => 'seat',
        'ulëset e perparme' => 'seat',
        'ulëset e pasme' => 'rear_seat',
        'ulese e pasme' => 'rear_seat',
        'mbështetëset e kokës' => 'headrest',
        'mbeshtetëset e kokes' => 'headrest',
        'mbeshtetëset e kokës' => 'headrest',
        'rripat e sigurisë' => 'seatbelt',
        'rripat e sigurise' => 'seatbelt',
        'tabela e instrumenteve' => 'instrument_cluster',
        'ekrani multimedial' => 'infotainment',
        'ventilatorët e kabinës' => 'blower_motor',
        'ventilatorët e kabines' => 'blower_motor',
        'paneli i komandimit' => 'dashboard',
        'pedali i gazit' => 'accelerator_pedal',
        'pedali i frenës' => 'brake_pedal',
        'pedali i frene' => 'brake_pedal',
        'pedali i tufës' => 'clutch_pedal',
        'pedali i tufes' => 'clutch_pedal',
        'leva e shpejtësive' => 'gear_lever',
        'leva e shpejtesive' => 'gear_lever',
        'mbështetësja e krahut' => 'armrest',
        'mbeshtetësja e krahut' => 'armrest',
        'dyshemeja e brendshme' => 'floor_mat',
        'ndriçimi i brendshëm' => 'interior_light',
        'ndriçimi i brendsh' => 'interior_light',
        'instalimi elektrik' => 'wiring_harness',
        'sensorët e parkimit' => 'parking_sensor',
        'sensorët e park' => 'parking_sensor',
        'kamera e pasme' => 'backup_camera',
        'moduli i airbagut' => 'airbag_module',
        'moduli i airbag' => 'airbag_module',
        'çelësi i veturës' => 'car_key',
        'celesi i vetures' => 'car_key',
        'sensori i boshtit të krankut' => 'crankshaft_sensor',
        'sensori i boshtit te krankut' => 'crankshaft_sensor',
        'sensori i boshtit me gunga' => 'camshaft_sensor',
        'sensori i trokitjes' => 'knock_sensor',
        'sensori i shiut' => 'rain_sensor',
        'sensori i dritës' => 'light_sensor',
        'sensori i drites' => 'light_sensor',
        'sensori i temperaturës' => 'temperature_sensor',
        'sensori i temperatur' => 'temperature_sensor',
        'sensori maf' => 'maf_sensor',
        'sensori map' => 'map_sensor',
        'lambda sonda' => 'lambda_sensor',
        'valvula egr' => 'egr_valve',
        'valvula egr.' => 'egr_valve',
        'sensori egr' => 'egr_valve',
        'tubi i shkarkimit' => 'exhaust_pipe',
        'marmita' => 'muffler',
        'silenceri' => 'muffler',
        'izolimi i zhurmës' => 'noise_insulation',
        'izolimi i zhurm' => 'noise_insulation',
        'mburoja e motorit' => 'engine_cover',
        'kllapat e motorit' => 'engine_mount',
        'blloku i motorit' => 'engine_block',
        'koka e motorit' => 'cylinder_head',
        'zinxhiri i motorit' => 'timing_chain',
        'rripi i motorit' => 'drive_belt',
        'volanti i motorit' => 'flywheel',
        'amortizerët' => 'shock_absorber',
        'amortizeret' => 'shock_absorber',
        'amortizues' => 'shock_absorber',
        'amortizator' => 'shock_absorber',
        'bllok motori' => 'engine_block',
        'koka motori' => 'cylinder_head',
        'zinxhir motori' => 'timing_chain',
        'rrip motori' => 'drive_belt',
        'turbina' => 'turbo',
        'intercooler' => 'intercooler',
        'termostati' => 'thermostat',
        'alternatori' => 'alternator',
        'starteri' => 'starter',
        'bateria' => 'battery',
        'qirinjtë' => 'spark_plug',
        'qirinjt' => 'spark_plug',
        'qirinj' => 'spark_plug',
        'bobinat' => 'ignition_coil',
        'injektorët' => 'injector',
        'injektor' => 'injector',
        'injektorë' => 'injector',
        'diferenciali' => 'differential',
        'gjysmëboshtet' => 'cv_joint',
        'gjysmeboshtet' => 'cv_joint',
        'kaliperët' => 'brake_caliper',
        'kaliper' => 'brake_caliper',
        'servo freni' => 'brake_booster',
        'abs moduli' => 'abs_module',
        'moduli abs' => 'abs_module',
        'timoni' => 'steering_wheel',
        'timon' => 'steering_wheel',
        'timonë' => 'steering_wheel',
        'timone' => 'steering_wheel',
        'kremaliera' => 'steering_rack',
        'stabilizatori' => 'sway_bar',
        'kushinetat' => 'bushing',
        'kushinet' => 'bushing',
        'fellnet' => 'wheel_rim',
        'gomat' => 'tire',
        'goma' => 'tire',
        'baltambruesit' => 'fender',
        'baltambrues' => 'fender',
        'dyer' => 'door',
        'dera' => 'door',
        'bagazhi' => 'trunk',
        'spoileri' => 'spoiler',
        'çatia' => 'roof',
        'catia' => 'roof',
        'antena' => 'antenna',
        'airbagët' => 'airbag',
        'airbaget' => 'airbag',
        'airbag' => 'airbag',
        'kilometrazhi' => 'odometer',
        'takometri' => 'tachometer',
        'radioja' => 'radio',
        'altoparlantët' => 'speaker',
        'altoparlantet' => 'speaker',
        'kondicioneri' => 'ac_compressor',
        'tapiceria' => 'upholstery',
        'siguresat' => 'fuse',
        'sigur' => 'fuse',
        'reletë' => 'relay',
        'relete' => 'relay',
        'imobilizatori' => 'immobilizer',
        'katalizatori' => 'catalytic_converter',
        'resonatori' => 'exhaust_resonator',
        'kapaku i vajit' => 'oil_cap',
        'pistoni' => 'piston',
        'piston' => 'piston',
        'valvulat' => 'valve',
        'valvula' => 'valve',
        'valvul' => 'valve',
        'sustat' => 'coil_spring',
        'susta' => 'coil_spring',
        'targat' => 'license_plate',
        'targa' => 'license_plate',
        'sinjalizuesit' => 'indicator',
        'sinjalizues' => 'indicator',
        'radiatori' => 'radiator',
        'pompa e ujit' => 'water_pump',
        'tufa' => 'clutch',
        'clutch' => 'clutch',
        'ecu' => 'ecu',
        'moduli bcm' => 'bcm_module',
        'disqet' => 'brake_disc',
        'disqe' => 'brake_disc',
        'disq' => 'brake_disc',
        'plaket' => 'brake_pad',
        'plaka' => 'brake_pad',
    ];

    /** @var array<string, string> single token => component key */
    private const TOKENS = [
        'turbolader' => 'turbo',
        'turbocharger' => 'turbo',
        'lenkrad' => 'steering_wheel',
        'volan' => 'steering_wheel',
        'frenave' => 'brake_pad',
        'frena' => 'brake_pad',
    ];

    /**
     * @return array<string, string> longest phrases first
     */
    public static function phrases(): array
    {
        static $sorted = null;
        if ($sorted !== null) {
            return $sorted;
        }

        $sorted = self::PHRASES;
        uksort($sorted, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        return $sorted;
    }

    public static function componentFromQuery(string $rawQuery): string
    {
        $haystack = mb_strtolower(trim($rawQuery));

        foreach (self::phrases() as $phrase => $key) {
            if (str_contains($haystack, $phrase)) {
                return $key;
            }
        }

        if (preg_match_all('/\p{L}[\p{L}\d\-]*/u', $haystack, $matches)) {
            foreach ($matches[0] as $token) {
                if (isset(self::TOKENS[$token])) {
                    return self::TOKENS[$token];
                }
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    public static function partsKeywords(): array
    {
        return [
            'autopjese', 'autopjesë', 'pjesë', 'pjese', 'pjeset', 'pjesa', 'pjesëve',
            'spare part', 'spare parts', 'car part', 'car parts', 'auto part', 'auto parts',
            'ersatzteil', 'ersatzteile', 'autoteil', 'autoteile', 'kfz-teil', 'kfzteil',
            'ricambi', 'ricambio', 'pièce', 'pièces', 'pieces auto',
            'onderdelen', 'auto-onderdelen', 'repuesto', 'repuestos', 'recambio', 'recambios',
            'części samochodowe', 'náhradní díly',
            'timon', 'timoni', 'timonë', 'timone', 'volan', 'volani',
            'turbina', 'turbin', 'amortiz', 'amortizer', 'amortizues',
            'disqet', 'disqe', 'disq', 'frenave', 'plaket', 'plaka',
            'filtri', 'filteri', 'pompa', 'sensori', 'moduli',
            'luftfeder', 'luftbalg', 'luftfederung', 'pneumat',
            'kremaliera', 'katalizator', 'radiator', 'alternator', 'starteri',
            'injektor', 'bobina', 'qirinj', 'bateria', 'gomat', 'fellnet',
        ];
    }
}
