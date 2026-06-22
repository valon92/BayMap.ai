<?php

namespace App\Support;

/**
 * Search + listing-match definitions for auto part component keys.
 */
class AutomotivePartsComponentRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        static $defs = null;
        if ($defs !== null) {
            return $defs;
        }

        $defs = [];
        foreach (self::entries() as $key => $entry) {
            $defs[$key] = self::build($entry);
        }

        return $defs;
    }

    /**
     * @param  array{de: string, en: string, alt?: string, serp?: array<int, string>}  $entry
     * @return array<string, mixed>
     */
    private static function build(array $entry): array
    {
        $de = $entry['de'];
        $en = $entry['en'];
        $alt = (string) ($entry['alt'] ?? '');
        $pattern = $alt !== '' ? $alt : self::regexFromTerms([$de, $en, ...(array) ($entry['serp'] ?? [])]);

        return [
            'query_patterns' => [],
            'title_regex' => '/\b('.$pattern.')\b/iu',
            'search' => AutomotivePartsLocale::searchMap(
                $de,
                $en,
                (array) ($entry['locales'] ?? []),
            ),
            'serp_extra' => (array) ($entry['serp'] ?? []),
        ];
    }

    /**
     * @param  array<int, string>  $terms
     */
    private static function regexFromTerms(array $terms): string
    {
        $parts = [];
        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }
            $parts[] = str_replace(' ', '\s*', preg_quote($term, '/'));
        }

        return implode('|', array_unique($parts));
    }

    /**
     * @return array<string, array{de: string, en: string, alt?: string, serp?: array<int, string>}>
     */
    private static function entries(): array
    {
        return [
            'engine_block' => ['de' => 'Motorblock', 'en' => 'engine block', 'serp' => ['short block', 'long block']],
            'cylinder_head' => ['de' => 'Zylinderkopf', 'en' => 'cylinder head'],
            'piston' => ['de' => 'Kolben', 'en' => 'piston'],
            'piston_ring' => ['de' => 'Kolbenring', 'en' => 'piston ring', 'serp' => ['Kolbenringsatz']],
            'connecting_rod' => ['de' => 'Pleuel', 'en' => 'connecting rod', 'serp' => ['Pleuelstange']],
            'crankshaft' => ['de' => 'Kurbelwelle', 'en' => 'crankshaft'],
            'camshaft' => ['de' => 'Nockenwelle', 'en' => 'camshaft'],
            'valve' => ['de' => 'Ventil', 'en' => 'engine valve', 'alt' => 'ventil|einlassventil|auslassventil|engine\s*valve'],
            'valve_spring' => ['de' => 'Ventilfeder', 'en' => 'valve spring'],
            'timing_chain' => ['de' => 'Steuerkette', 'en' => 'timing chain', 'serp' => ['Steuerkettensatz']],
            'drive_belt' => ['de' => 'Keilriemen', 'en' => 'drive belt', 'serp' => ['serpentine belt', 'V-ribbed belt', 'Riemensatz']],
            'intercooler' => ['de' => 'Ladeluftkühler', 'en' => 'intercooler'],
            'intake_manifold' => ['de' => 'Ansaugkrümmer', 'en' => 'intake manifold', 'serp' => ['Ansaugbrücke']],
            'exhaust_manifold' => ['de' => 'Abgaskrümmer', 'en' => 'exhaust manifold'],
            'air_filter' => ['de' => 'Luftfilter', 'en' => 'air filter'],
            'oil_filter' => ['de' => 'Ölfilter', 'en' => 'oil filter'],
            'fuel_filter' => ['de' => 'Kraftstofffilter', 'en' => 'fuel filter', 'serp' => ['Benzinfilter', 'Dieselfilter']],
            'cabin_filter' => ['de' => 'Innenraumfilter', 'en' => 'cabin filter', 'serp' => ['Pollenfilter', 'cabin air filter']],
            'oil_pump' => ['de' => 'Ölpumpe', 'en' => 'oil pump'],
            'radiator_fan' => ['de' => 'Kühlerlüfter', 'en' => 'radiator fan', 'serp' => ['Kühlerventilator']],
            'thermostat' => ['de' => 'Thermostat', 'en' => 'thermostat'],
            'coolant_reservoir' => ['de' => 'Ausgleichsbehälter', 'en' => 'coolant reservoir', 'serp' => ['expansion tank']],
            'ignition_coil' => ['de' => 'Zündspule', 'en' => 'ignition coil'],
            'fuel_pump' => ['de' => 'Kraftstoffpumpe', 'en' => 'fuel pump', 'serp' => ['Benzinpumpe']],
            'fuel_tank' => ['de' => 'Kraftstofftank', 'en' => 'fuel tank', 'serp' => ['Benzintank']],
            'maf_sensor' => ['de' => 'Luftmassenmesser', 'en' => 'MAF sensor', 'serp' => ['mass air flow']],
            'map_sensor' => ['de' => 'Ansaugdrucksensor', 'en' => 'MAP sensor'],
            'lambda_sensor' => ['de' => 'Lambdasonde', 'en' => 'lambda sensor', 'serp' => ['oxygen sensor', 'O2 sensor']],
            'ecu' => ['de' => 'Steuergerät', 'en' => 'ECU', 'alt' => 'steuergerät|engine\s*control\s*unit|ecu|control\s*unit'],
            'gearbox' => ['de' => 'Getriebe', 'en' => 'gearbox', 'serp' => ['transmission', 'Schaltgetriebe', 'Automatikgetriebe']],
            'flywheel' => ['de' => 'Schwungrad', 'en' => 'flywheel'],
            'differential' => ['de' => 'Differential', 'en' => 'differential'],
            'driveshaft' => ['de' => 'Antriebswelle', 'en' => 'driveshaft'],
            'cv_joint' => ['de' => 'Gelenkwelle', 'en' => 'CV joint', 'serp' => ['Antriebswelle', 'Gelenkscheibe']],
            'propshaft' => ['de' => 'Kardanwelle', 'en' => 'propshaft', 'serp' => ['cardan shaft']],
            'brake_caliper' => ['de' => 'Bremssattel', 'en' => 'brake caliper'],
            'brake_master_cylinder' => ['de' => 'Hauptbremszylinder', 'en' => 'brake master cylinder'],
            'brake_booster' => ['de' => 'Bremskraftverstärker', 'en' => 'brake booster'],
            'brake_line' => ['de' => 'Bremsleitung', 'en' => 'brake line', 'serp' => ['brake hose']],
            'handbrake' => ['de' => 'Handbremse', 'en' => 'handbrake', 'serp' => ['parking brake']],
            'abs_module' => ['de' => 'ABS Steuergerät', 'en' => 'ABS module', 'alt' => 'abs\s*steuergerät|abs\s*modul|abs\s*module|abs\s*pump'],
            'steering_column' => ['de' => 'Lenksäule', 'en' => 'steering column'],
            'steering_rack' => ['de' => 'Lenkgetriebe', 'en' => 'steering rack', 'serp' => ['rack and pinion']],
            'power_steering_pump' => ['de' => 'Servolenkungspumpe', 'en' => 'power steering pump', 'serp' => ['Servopumpe']],
            'tie_rod_end' => ['de' => 'Spurstangenkopf', 'en' => 'tie rod end', 'serp' => ['Spurstangenkugel']],
            'control_arm' => ['de' => 'Querlenker', 'en' => 'control arm', 'serp' => ['wishbone', 'track control arm']],
            'coil_spring' => ['de' => 'Schraubenfeder', 'en' => 'coil spring', 'serp' => ['Feder']],
            'sway_bar' => ['de' => 'Stabilisator', 'en' => 'sway bar', 'serp' => ['anti roll bar']],
            'sway_bar_link' => ['de' => 'Koppelstange', 'en' => 'sway bar link', 'serp' => ['Stabilisatorstange']],
            'bushing' => ['de' => 'Gummilager', 'en' => 'bushing', 'serp' => ['control arm bush']],
            'wheel_hub' => ['de' => 'Radnabe', 'en' => 'wheel hub', 'serp' => ['wheel bearing hub']],
            'wheel_rim' => ['de' => 'Felge', 'en' => 'wheel rim', 'serp' => ['alloy wheel']],
            'tire' => ['de' => 'Reifen', 'en' => 'tire', 'serp' => ['tyre', 'summer tire', 'winter tire']],
            'wheel_bolt' => ['de' => 'Radschraube', 'en' => 'wheel bolt', 'serp' => ['wheel nut']],
            'valve_cover' => ['de' => 'Ventildeckel', 'en' => 'valve cover'],
            'front_bumper' => ['de' => 'Stoßstange vorne', 'en' => 'front bumper', 'alt' => 'front\s*bumper|stoßstange\s*vo|stoßstange'],
            'rear_bumper' => ['de' => 'Stoßstange hinten', 'en' => 'rear bumper', 'alt' => 'rear\s*bumper|stoßstange\s*hi'],
            'grille' => ['de' => 'Kühlergrill', 'en' => 'grille', 'serp' => ['front grille']],
            'fender' => ['de' => 'Kotflügel', 'en' => 'fender', 'serp' => ['wing panel']],
            'door_hinge' => ['de' => 'Türscharnier', 'en' => 'door hinge'],
            'door_handle' => ['de' => 'Türgriff', 'en' => 'door handle'],
            'rear_glass' => ['de' => 'Heckscheibe', 'en' => 'rear window', 'serp' => ['back glass']],
            'side_window' => ['de' => 'Seitenscheibe', 'en' => 'side window'],
            'window_regulator' => ['de' => 'Fensterheber', 'en' => 'window regulator'],
            'wiper_motor' => ['de' => 'Wischermotor', 'en' => 'wiper motor'],
            'tail_light' => ['de' => 'Rückleuchte', 'en' => 'tail light', 'serp' => ['rear light']],
            'fog_light' => ['de' => 'Nebelscheinwerfer', 'en' => 'fog light'],
            'indicator' => ['de' => 'Blinker', 'en' => 'indicator', 'serp' => ['turn signal']],
            'license_plate' => ['de' => 'Kennzeichenhalter', 'en' => 'license plate holder', 'serp' => ['number plate']],
            'trunk' => ['de' => 'Kofferraum', 'en' => 'trunk', 'serp' => ['boot']],
            'trunk_lid' => ['de' => 'Heckklappe', 'en' => 'trunk lid', 'serp' => ['tailgate']],
            'spoiler' => ['de' => 'Spoiler', 'en' => 'spoiler', 'serp' => ['rear spoiler']],
            'roof' => ['de' => 'Dach', 'en' => 'roof panel', 'serp' => ['sunroof']],
            'antenna' => ['de' => 'Antenne', 'en' => 'antenna'],
            'rear_seat' => ['de' => 'Rücksitz', 'en' => 'rear seat', 'serp' => ['back seat']],
            'headrest' => ['de' => 'Kopfstütze', 'en' => 'headrest'],
            'seatbelt' => ['de' => 'Sicherheitsgurt', 'en' => 'seat belt'],
            'airbag' => ['de' => 'Airbag', 'en' => 'airbag'],
            'instrument_cluster' => ['de' => 'Kombiinstrument', 'en' => 'instrument cluster', 'serp' => ['dashboard cluster']],
            'odometer' => ['de' => 'Tachometer', 'en' => 'odometer', 'serp' => ['speedometer cluster']],
            'tachometer' => ['de' => 'Drehzahlmesser', 'en' => 'tachometer'],
            'infotainment' => ['de' => 'Multimedia', 'en' => 'infotainment', 'serp' => ['navigation unit', 'car radio screen']],
            'radio' => ['de' => 'Autoradio', 'en' => 'car radio'],
            'speaker' => ['de' => 'Lautsprecher', 'en' => 'speaker'],
            'ac_compressor' => ['de' => 'Klimakompressor', 'en' => 'AC compressor', 'serp' => ['air conditioning compressor']],
            'blower_motor' => ['de' => 'Gebläsemotor', 'en' => 'blower motor', 'serp' => ['heater blower']],
            'dashboard' => ['de' => 'Armaturenbrett', 'en' => 'dashboard'],
            'accelerator_pedal' => ['de' => 'Gaspedal', 'en' => 'accelerator pedal'],
            'brake_pedal' => ['de' => 'Bremspedal', 'en' => 'brake pedal'],
            'clutch_pedal' => ['de' => 'Kupplungspedal', 'en' => 'clutch pedal'],
            'gear_lever' => ['de' => 'Schaltknauf', 'en' => 'gear lever', 'serp' => ['gear knob', 'shift knob']],
            'armrest' => ['de' => 'Armlehne', 'en' => 'armrest'],
            'upholstery' => ['de' => 'Sitzbezug', 'en' => 'upholstery', 'serp' => ['seat cover']],
            'floor_mat' => ['de' => 'Fußmatte', 'en' => 'floor mat', 'serp' => ['car mat']],
            'interior_light' => ['de' => 'Innenraumleuchte', 'en' => 'interior light'],
            'fuse' => ['de' => 'Sicherung', 'en' => 'fuse'],
            'relay' => ['de' => 'Relais', 'en' => 'relay'],
            'wiring_harness' => ['de' => 'Kabelbaum', 'en' => 'wiring harness'],
            'parking_sensor' => ['de' => 'Parksensor', 'en' => 'parking sensor', 'serp' => ['PDC sensor']],
            'backup_camera' => ['de' => 'Rückfahrkamera', 'en' => 'backup camera', 'serp' => ['rear view camera']],
            'airbag_module' => ['de' => 'Airbag Steuergerät', 'en' => 'airbag module'],
            'bcm_module' => ['de' => 'BCM Steuergerät', 'en' => 'BCM module', 'alt' => 'bcm|body\s*control\s*module'],
            'car_key' => ['de' => 'Autoschlüssel', 'en' => 'car key', 'serp' => ['remote key', 'key fob']],
            'immobilizer' => ['de' => 'Wegfahrsperre', 'en' => 'immobilizer'],
            'start_stop_system' => ['de' => 'Start Stop Anlage', 'en' => 'start stop system'],
            'rain_sensor' => ['de' => 'Regensensor', 'en' => 'rain sensor'],
            'light_sensor' => ['de' => 'Lichtsensor', 'en' => 'light sensor'],
            'temperature_sensor' => ['de' => 'Temperatursensor', 'en' => 'temperature sensor', 'serp' => ['coolant temp sensor']],
            'tpms_sensor' => ['de' => 'Reifendrucksensor', 'en' => 'TPMS sensor', 'serp' => ['tire pressure sensor']],
            'catalytic_converter' => [
                'de' => 'Katalysator',
                'en' => 'catalytic converter',
                'alt' => 'katalysator|katalizator|catalytic\s*converter|kat(?:\.|\s|$)',
                'serp' => ['Katalysator', 'catalytic converter', 'Abgasreinigung'],
                'locales' => [
                    'FR' => 'catalyseur',
                    'IT' => 'catalizzatore',
                    'ES' => 'catalizador',
                    'PT' => 'catalisador',
                    'PL' => 'katalizator',
                    'NL' => 'katalysator',
                ],
            ],
            'dpf_filter' => ['de' => 'Rußpartikelfilter', 'en' => 'DPF filter', 'serp' => ['diesel particulate filter']],
            'exhaust_pipe' => ['de' => 'Abgasrohr', 'en' => 'exhaust pipe'],
            'muffler' => ['de' => 'Schalldämpfer', 'en' => 'muffler', 'serp' => ['silencer']],
            'exhaust_resonator' => ['de' => 'Resonator', 'en' => 'exhaust resonator'],
            'oil_cap' => ['de' => 'Öldeckel', 'en' => 'oil cap', 'serp' => ['oil filler cap']],
            'dipstick' => ['de' => 'Ölmessstab', 'en' => 'dipstick'],
            'washer_reservoir' => ['de' => 'Scheibenwaschbehälter', 'en' => 'washer reservoir'],
            'washer_nozzle' => ['de' => 'Waschdüse', 'en' => 'washer nozzle', 'serp' => ['windshield washer jet']],
            'engine_mount' => ['de' => 'Motorlager', 'en' => 'engine mount', 'serp' => ['engine bracket']],
            'noise_insulation' => ['de' => 'Dämmmatte', 'en' => 'noise insulation', 'serp' => ['engine bay insulation']],
            'engine_cover' => ['de' => 'Motorabdeckung', 'en' => 'engine cover', 'serp' => ['engine bay cover']],
            'crankshaft_sensor' => ['de' => 'Kurbelwellensensor', 'en' => 'crankshaft sensor', 'serp' => ['crank sensor']],
            'camshaft_sensor' => ['de' => 'Nockenwellensensor', 'en' => 'camshaft sensor', 'serp' => ['cam sensor']],
            'knock_sensor' => ['de' => 'Klopfsensor', 'en' => 'knock sensor'],
            'egr_valve' => ['de' => 'AGR Ventil', 'en' => 'EGR valve', 'serp' => ['AGR-Ventil', 'exhaust gas recirculation']],
        ];
    }
}
