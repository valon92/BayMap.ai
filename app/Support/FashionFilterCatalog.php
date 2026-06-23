<?php

namespace App\Support;

/**
 * Fashion AI filter options (type + brand) for results UI and listing match.
 */
class FashionFilterCatalog
{
    /** @var array<int, string> */
    public const PRODUCT_TYPES = [
        't_shirt', 'polo_shirt', 'shirt', 'blouse', 'tank_top', 'hoodie', 'sweatshirt', 'sweater',
        'cardigan', 'jacket', 'coat', 'blazer', 'suit', 'vest', 'jeans', 'pants', 'chinos', 'shorts',
        'joggers', 'leggings', 'skirt', 'dress', 'jumpsuit', 'tracksuit', 'pajamas', 'underwear', 'bra',
        'socks', 'tights', 'sneakers', 'running_shoes', 'boots', 'sandals', 'slippers', 'high_heels',
        'loafers', 'belt', 'hat', 'cap', 'beanie', 'scarf', 'gloves', 'tie', 'bow_tie', 'sunglasses',
        'watch', 'ring', 'necklace', 'bracelet', 'earrings', 'handbag', 'backpack', 'wallet', 'suitcase',
        'crossbody_bag', 'tote_bag', 'sportswear', 'swimwear', 'activewear', 'formal_wear', 'casual_wear',
        'streetwear', 'luxury_fashion', 'kidswear', 'maternity_wear', 'workwear', 'sleepwear', 'outerwear',
        'denim', 'loungewear', 'leather_jacket', 'bomber_jacket', 'puffer_jacket', 'trench_coat', 'raincoat',
        'crop_top', 'tunic', 'kaftan', 'bikini', 'swimsuit', 'flip_flops', 'clogs', 'espadrilles',
        'oxford_shoes', 'derby_shoes', 'chelsea_boots', 'hiking_boots', 'duffel_bag', 'messenger_bag',
        'clutch_bag', 'brooch', 'cufflinks', 'fashion_accessories', 'jewelry', 'eyewear', 'perfume',
        'beauty_products', 'cosmetics', 'hair_accessories', 'fashion_collection',
    ];

    /** @var array<int, string> */
    public const BRANDS = [
        'nike', 'adidas', 'puma', 'under_armour', 'new_balance', 'reebok', 'converse', 'vans', 'levis',
        'tommy_hilfiger', 'calvin_klein', 'ralph_lauren', 'lacoste', 'hugo_boss', 'armani', 'versace',
        'gucci', 'prada', 'dolce_gabbana', 'fendi', 'valentino', 'burberry', 'alexander_mcqueen',
        'balenciaga', 'loewe', 'louis_vuitton', 'chanel', 'dior', 'hermes', 'saint_laurent', 'givenchy',
        'balmain', 'moncler', 'zara', 'h_m', 'mango', 'massimo_dutti', 'uniqlo', 'cos', 'the_north_face',
        'columbia', 'patagonia', 'timberland', 'salomon', 'stone_island', 'cp_company', 'off_white',
        'supreme', 'palm_angels', 'fear_of_god',
    ];

    /** @var array<string, array<int, string>> */
    private const BRAND_NEEDLES = [
        'under_armour' => ['under armour', 'underarmour'],
        'new_balance' => ['new balance'],
        'levis' => ["levi's", 'levis', 'levi'],
        'tommy_hilfiger' => ['tommy hilfiger'],
        'calvin_klein' => ['calvin klein'],
        'ralph_lauren' => ['ralph lauren', 'polo ralph'],
        'hugo_boss' => ['hugo boss', 'boss'],
        'dolce_gabbana' => ['dolce & gabbana', 'dolce gabbana', 'd&g'],
        'alexander_mcqueen' => ['alexander mcqueen', 'mcqueen'],
        'louis_vuitton' => ['louis vuitton', 'lv'],
        'saint_laurent' => ['saint laurent', 'yves saint laurent', 'ysl'],
        'h_m' => ['h&m', 'hm '],
        'massimo_dutti' => ['massimo dutti'],
        'the_north_face' => ['the north face', 'north face'],
        'stone_island' => ['stone island'],
        'cp_company' => ['cp company', 'c.p. company'],
        'off_white' => ['off-white', 'off white'],
        'palm_angels' => ['palm angels'],
        'fear_of_god' => ['fear of god', 'fog essentials', 'essentials'],
    ];

    /** @var array<string, array<int, string>> */
    private const TYPE_NEEDLES = [
        't_shirt' => ['t-shirt', 't shirt', 'tee', 'tshirt'],
        'polo_shirt' => ['polo shirt', 'polo'],
        'tank_top' => ['tank top', 'tank'],
        'running_shoes' => ['running shoe', 'running shoes', 'runner'],
        'high_heels' => ['high heel', 'high heels', 'heel'],
        'bow_tie' => ['bow tie', 'bowtie'],
        'crossbody_bag' => ['crossbody', 'cross body'],
        'tote_bag' => ['tote bag', 'tote'],
        'flip_flops' => ['flip flop', 'flip-flop', 'flip flops'],
        'oxford_shoes' => ['oxford shoe', 'oxford shoes', 'oxford'],
        'derby_shoes' => ['derby shoe', 'derby shoes'],
        'chelsea_boots' => ['chelsea boot', 'chelsea boots'],
        'hiking_boots' => ['hiking boot', 'hiking boots'],
        'duffel_bag' => ['duffel', 'duffle'],
        'messenger_bag' => ['messenger bag', 'messenger'],
        'clutch_bag' => ['clutch bag', 'clutch'],
        'leather_jacket' => ['leather jacket'],
        'bomber_jacket' => ['bomber jacket', 'bomber'],
        'puffer_jacket' => ['puffer jacket', 'puffer', 'down jacket'],
        'trench_coat' => ['trench coat', 'trench'],
        'crop_top' => ['crop top'],
        'hair_accessories' => ['hair accessory', 'hair clip', 'scrunchie', 'headband'],
        'fashion_accessories' => ['accessory', 'accessories'],
        'beauty_products' => ['beauty product', 'beauty'],
        'fashion_collection' => ['collection', 'fashion collection'],
        'sneakers' => ['sneaker', 'trainer', 'trainers', 'patika', 'atlete'],
        'blouse' => ['bluz', 'bluze', 'bluse', 'top'],
        'jeans' => ['jeans', 'xhinse', 'xhins', 'denim', 'jean'],
        'shoes' => ['shoe', 'këpucë', 'kepuce', 'mbathje'],
    ];

    /**
     * @return array<int, string>
     */
    public static function brandNeedles(string $brand): array
    {
        $slug = self::slugify($brand);
        if (isset(self::BRAND_NEEDLES[$slug])) {
            return self::BRAND_NEEDLES[$slug];
        }

        $spaced = str_replace('_', ' ', $slug);

        return array_values(array_unique([$spaced, $slug]));
    }

    /**
     * @return array<int, string>
     */
    public static function typeNeedles(string $type): array
    {
        $slug = self::slugify(FashionIntentParser::normalizeType($type));
        if ($slug === '') {
            return [];
        }

        $needles = self::TYPE_NEEDLES[$slug] ?? [];
        $spaced = str_replace('_', ' ', $slug);
        $hyphen = str_replace('_', '-', $slug);

        return array_values(array_unique(array_merge([$spaced, $slug, $hyphen], $needles)));
    }

    public static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['&', "'", '.'], '', $value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;

        return trim($value, '_');
    }
}
