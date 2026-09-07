<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Support\SwitchCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SwitchCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCategories();
        $this->seedBlogPosts();

        $admin = User::updateOrCreate(
            ['email' => 'admin@demo.com'],
            [
                'name' => 'Store Admin',
                'phone' => '+254700000001',
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make('admin123'),
            ]
        );

        $vendorUser = User::updateOrCreate(
            ['email' => 'vendor@networkswitches.test'],
            [
                'name' => 'Network Switches Kenya',
                'phone' => '+254700000002',
                'role' => 'vendor',
                'status' => 'active',
                'password' => Hash::make('vendor123'),
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@networkswitches.test'],
            [
                'name' => 'Sample Customer',
                'phone' => '+254700000003',
                'role' => 'customer',
                'status' => 'active',
                'password' => Hash::make('customer123'),
            ]
        );

        $vendor = Vendor::updateOrCreate(
            ['user_id' => $vendorUser->id],
            [
                'shop_name' => 'Network Switches Kenya',
                'slug' => 'network-switches-kenya',
                'description' => 'Kenya\'s specialist network switch store',
                'phone' => '+254700111222',
                'address' => 'Nairobi, Kenya',
                'is_approved' => true,
            ]
        );

        $categoryBySlug = Category::pluck('id', 'slug');

        foreach ($this->products() as $row) {
            $categoryId = $categoryBySlug[$row['category']] ?? null;
            if (! $categoryId) {
                continue;
            }

            $product = Product::firstOrCreate(
                ['sku' => $row['sku']],
                [
                    'vendor_id' => $vendor->id,
                    'category_id' => $categoryId,
                    'name' => $row['name'],
                    'slug' => $this->uniqueSlug($row['name']),
                    'price' => $row['price'],
                    'status' => 'active',
                ]
            );

            $product->update(array_merge(
                [
                    'vendor_id' => $vendor->id,
                    'category_id' => $categoryId,
                    'name' => $row['name'],
                    'description' => $this->description($row),
                    'meta_description' => $this->metaDescription($row),
                    'price' => $row['price'],
                    'compare_at_price' => $row['compare_at_price'] ?? null,
                    'stock' => $row['stock'],
                    'status' => 'active',
                    'brand' => $row['brand'],
                    'model_number' => $row['model'],
                ],
                $this->switchAttributes($row)
            ));
        }
    }

    private function seedCategories(): void
    {
        $definitions = SwitchCatalog::definitions();

        foreach ($definitions as $slug => $def) {
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $def['name'],
                    'type' => $def['type'],
                    'meta_description' => $def['meta'],
                    'description' => $def['description'],
                ]
            );
        }

        foreach ($definitions as $slug => $def) {
            if (empty($def['parent'])) {
                continue;
            }

            $child = Category::where('slug', $slug)->first();
            $parent = Category::where('slug', $def['parent'])->first();

            if ($child && $parent) {
                $child->parent_id = $parent->id;
                $child->save();
            }
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function seedBlogPosts(): void
    {
        foreach ($this->blogPosts() as $post) {
            Page::updateOrCreate(
                ['slug' => $post['slug']],
                [
                    'title' => $post['title'],
                    'heading_two' => $post['title'],
                    'meta_title' => $post['meta_title'],
                    'meta_description' => $post['meta_description'],
                    'type' => 'post',
                    'category' => $post['category'],
                    'body' => $post['body'],
                ]
            );
        }
    }

    /**
     * @return array<int, array{slug: string, title: string, meta_title: string, meta_description: string, body: string}>
     */
    private function blogPosts(): array
    {
        return [
            [
                'slug' => 'how-to-choose-a-network-switch-in-kenya',
                'title' => 'How to Choose a Network Switch in Kenya',
                'meta_title' => 'How to Choose a Network Switch in Kenya',
                'category' => 'buying-guides',
                'meta_description' => 'A practical guide to choosing the right network switch by port count, PoE, speed and management for your network in Kenya.',
                'body' => implode('', [
                    '<p>Choosing a network switch comes down to four questions: how many devices you need to connect, whether they need power, what speed you require, and whether you need management features.</p>',
                    '<h2>Step 1: Count your devices</h2>',
                    '<p>Start with the number of devices that will plug into the switch. A 5 or 8 port switch suits a home or small office, while 16, 24 and 48 port switches suit growing offices, hotels and schools.</p>',
                    '<h2>Step 2: Decide on PoE</h2>',
                    '<p>Power over Ethernet delivers power and data over one cable. You need PoE if you are connecting <a href="/solutions/cctv-switches">CCTV cameras</a>, <a href="/solutions/wifi-access-point-switches">WiFi access points</a> or VoIP phones.</p>',
                    '<h2>Step 3: Match speed</h2>',
                    '<p><a href="/network-switches/gigabit-switches">Gigabit</a> is the modern standard. Choose 2.5G or 10G switches for high-performance workstations, servers and aggregation.</p>',
                    '<h2>Step 4: Choose management</h2>',
                    '<p>Choose <a href="/network-switches/unmanaged-switches">unmanaged</a> for plug-and-play, and <a href="/network-switches/managed-switches">managed</a> when you need VLANs, QoS or monitoring.</p>',
                    '<p>Use our <a href="/switch-finder">Switch Finder</a> to get a recommendation based on your exact requirements.</p>',
                ]),
            ],
            [
                'slug' => 'poe-vs-poe-plus-vs-poe-plus-plus',
                'title' => 'PoE vs PoE+ vs PoE++: What Is PoE Budget?',
                'meta_title' => 'PoE vs PoE+ vs PoE++ Explained',
                'category' => 'poe-guides',
                'meta_description' => 'Understand PoE standards (802.3af, 802.3at, 802.3bt), per-port wattage and PoE budget before buying a PoE switch in Kenya.',
                'body' => implode('', [
                    '<p>Power over Ethernet (PoE) lets a single cable carry both data and power. The standard determines how much power each port can deliver.</p>',
                    '<ul>',
                    '<li><strong>PoE (802.3af)</strong> - up to 15.4W per port, enough for basic IP cameras and phones.</li>',
                    '<li><strong>PoE+ (802.3at)</strong> - up to 30W per port, the common choice for CCTV cameras and access points.</li>',
                    '<li><strong>PoE++ (802.3bt)</strong> - up to 60W or 90W per port, for PTZ cameras and high-power devices.</li>',
                    '</ul>',
                    '<h2>What is PoE budget?</h2>',
                    '<p>The PoE budget is the total power the switch can supply across all PoE ports at once. Check it against your devices so you never overload the switch. Browse <a href="/network-switches/poe-switches">PoE switches</a> by budget to match your installation.</p>',
                ]),
            ],
            [
                'slug' => 'managed-vs-unmanaged-network-switch',
                'title' => 'Managed vs Unmanaged Network Switch',
                'meta_title' => 'Managed vs Unmanaged Network Switch',
                'category' => 'buying-guides',
                'meta_description' => 'Learn the difference between managed, smart and unmanaged network switches and which one to choose for your network in Kenya.',
                'body' => implode('', [
                    '<p><a href="/network-switches/unmanaged-switches">Unmanaged switches</a> are plug-and-play, with no configuration. They are ideal for homes, cyber cafes and simple expansion.</p>',
                    '<p><a href="/network-switches/managed-switches">Managed switches</a> add VLANs, QoS, link aggregation and monitoring, giving you full control for offices and enterprises.</p>',
                    '<p><a href="/network-switches/smart-managed-switches">Smart managed switches</a> sit in between, offering essential features through a simple web interface at a lower price.</p>',
                ]),
            ],
            [
                'slug' => 'best-switch-for-cctv-cameras-in-kenya',
                'title' => 'Best Switch for CCTV Cameras in Kenya',
                'meta_title' => 'Best PoE Switch for CCTV Cameras in Kenya',
                'category' => 'cctv-networking',
                'meta_description' => 'How to choose a PoE switch for CCTV cameras, including port count, PoE budget and compatibility with Hikvision and Dahua systems.',
                'body' => implode('', [
                    '<p>A PoE switch powers IP cameras directly, removing the need for separate power adapters. Match the switch port count to the number of cameras, and add headroom for future expansion.</p>',
                    '<p>For an 8 camera installation choose an <a href="/network-switches/8-port-poe-switches">8 port PoE switch</a>; for 16 cameras a <a href="/network-switches/16-port-poe-switches">16 port PoE switch</a>; and for larger sites a <a href="/network-switches/24-port-poe-switches">24 port PoE switch</a>.</p>',
                    '<p>Check that the total PoE budget covers all cameras, and look for SFP uplinks if you need to link the switch back over fibre. See our <a href="/solutions/cctv-switches">CCTV switch</a> range.</p>',
                ]),
            ],
            [
                'slug' => 'what-are-sfp-and-sfp-plus-ports',
                'title' => 'What Are SFP and SFP+ Ports?',
                'meta_title' => 'What Are SFP and SFP+ Ports?',
                'category' => 'fiber-sfp',
                'meta_description' => 'Understand SFP and SFP+ uplink ports on network switches and when you need them for fibre connectivity in Kenya.',
                'body' => implode('', [
                    '<p>SFP and SFP+ are uplink ports that accept fibre transceiver modules, letting you connect switches over long distances or to high-speed links.</p>',
                    '<p><strong>SFP</strong> supports 1 Gigabit, while <strong>SFP+</strong> supports 10 Gigabit. Choose <a href="/network-switches/sfp-switches">SFP switches</a> for standard fibre uplinks and <a href="/network-switches/sfp-plus-switches">SFP+ switches</a> for high-speed aggregation.</p>',
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function description(array $row): string
    {
        $parts = [];
        if ($row['port_count'] ?? null) {
            $parts[] = $row['port_count'].'-port';
        }
        if ($row['port_speed'] ?? null) {
            $parts[] = SwitchCatalog::speeds()[$row['port_speed']] ?? $row['port_speed'];
        }
        if (($row['poe_standard'] ?? null) === 'at') {
            $parts[] = 'PoE+';
        } elseif (($row['poe_standard'] ?? null) === 'bt') {
            $parts[] = 'PoE++';
        } elseif (($row['poe_ports'] ?? 0) > 0) {
            $parts[] = 'PoE';
        }
        $parts[] = SwitchCatalog::managementTypes()[$row['management_type'] ?? 'unmanaged'] ?? 'Unmanaged';

        $suffix = $parts !== [] ? ' '.implode(' ', $parts).' network switch' : ' network switch';

        return '<p>The '.$row['brand'].' '.$row['model'].' is a'.$suffix.' available in Kenya.</p>'
            .'<p>Compare price, port configuration, PoE capability and availability, or contact us for a quotation.</p>';
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function metaDescription(array $row): string
    {
        $summary = trim(($row['port_count'] ?? '').'-Port '.($row['brand'] ?? '').' '.($row['model'] ?? '').' network switch');

        return Str::limit($summary.' in Kenya. Check price, PoE, speed, management and stock availability.', 155, '');
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function switchAttributes(array $row): array
    {
        return [
            'port_count' => $row['port_count'] ?? null,
            'rj45_ports' => $row['rj45_ports'] ?? null,
            'sfp_ports' => $row['sfp_ports'] ?? 0,
            'sfp_plus_ports' => $row['sfp_plus_ports'] ?? 0,
            'sfp28_ports' => $row['sfp28_ports'] ?? 0,
            'qsfp_ports' => $row['qsfp_ports'] ?? 0,
            'poe_ports' => $row['poe_ports'] ?? 0,
            'poe_standard' => $row['poe_standard'] ?? null,
            'poe_budget' => $row['poe_budget'] ?? null,
            'poe_max_per_port' => $row['poe_max_per_port'] ?? null,
            'management_type' => $row['management_type'] ?? 'unmanaged',
            'layer' => $row['layer'] ?? null,
            'port_speed' => $row['port_speed'] ?? '1g',
            'uplink_type' => $row['uplink_type'] ?? null,
            'switching_capacity' => $row['switching_capacity'] ?? null,
            'forwarding_rate' => $row['forwarding_rate'] ?? null,
            'mac_table' => $row['mac_table'] ?? null,
            'vlan_support' => $row['vlan_support'] ?? false,
            'qos' => $row['qos'] ?? false,
            'stp' => $row['stp'] ?? false,
            'lacp' => $row['lacp'] ?? false,
            'snmp' => $row['snmp'] ?? false,
            'acl' => $row['acl'] ?? false,
            'rackmount' => $row['rackmount'] ?? false,
            'desktop' => $row['desktop'] ?? false,
            'din_rail' => $row['din_rail'] ?? false,
            'outdoor' => $row['outdoor'] ?? false,
            'industrial' => $row['industrial'] ?? false,
            'stackable' => $row['stackable'] ?? false,
            'cooling' => $row['cooling'] ?? 'fanless',
            'power_supply' => $row['power_supply'] ?? null,
            'power_consumption' => $row['power_consumption'] ?? null,
            'operating_temperature' => $row['operating_temperature'] ?? null,
            'warranty' => $row['warranty'] ?? null,
            'availability' => $row['availability'] ?? null,
            'applications' => $row['applications'] ?? [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function products(): array
    {
        return [
            // TP-Link
            [
                'name' => 'TP-Link TL-SG105 5-Port Gigabit Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG105', 'sku' => 'TPL-SG105',
                'category' => '5-port-switches', 'price' => 1900, 'compare_at_price' => 2400, 'stock' => 40,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => '3 Years', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SG108 8-Port Gigabit Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG108', 'sku' => 'TPL-SG108',
                'category' => '8-port-switches', 'price' => 2800, 'stock' => 35,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => '3 Years', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SG1005P 5-Port Gigabit PoE+ Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1005P', 'sku' => 'TPL-SG1005P',
                'category' => '5-port-poe-switches', 'price' => 8000, 'stock' => 30,
                'port_count' => 5, 'rj45_ports' => 5, 'poe_ports' => 4, 'poe_standard' => 'at', 'poe_budget' => 65, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['cctv', 'wifi', 'voip', 'home'],
            ],
            [
                'name' => 'TP-Link TL-SG108PE 8-Port Gigabit PoE+ Smart Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG108PE', 'sku' => 'TPL-SG108PE',
                'category' => '8-port-poe-switches', 'price' => 9800, 'compare_at_price' => 11500, 'stock' => 25,
                'port_count' => 8, 'rj45_ports' => 8, 'poe_ports' => 4, 'poe_standard' => 'at', 'poe_budget' => 64, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['cctv', 'wifi', 'office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1016DE 16-Port Gigabit Smart Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1016DE', 'sku' => 'TPL-SG1016DE',
                'category' => '16-port-switches', 'price' => 14500, 'stock' => 20,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'smart', 'port_speed' => '1g',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1024DE 24-Port Gigabit Smart Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1024DE', 'sku' => 'TPL-SG1024DE',
                'category' => '24-port-switches', 'price' => 19000, 'stock' => 18,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'smart', 'port_speed' => '1g',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG2428P 24-Port Gigabit Omada PoE+ Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG2428P', 'sku' => 'TPL-SG2428P',
                'category' => '24-port-poe-switches', 'price' => 26000, 'stock' => 12,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 250, 'poe_max_per_port' => 30,
                'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'switching_capacity' => '56 Gbps', 'applications' => ['cctv', 'wifi', 'office', 'enterprise'],
            ],
            [
                'name' => 'TP-Link TL-SG3428X 24-Port Gigabit Omada Managed Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG3428X', 'sku' => 'TPL-SG3428X',
                'category' => 'managed-switches', 'price' => 46000, 'stock' => 10,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_plus_ports' => 4, 'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['office', 'enterprise'],
            ],
            [
                'name' => 'TP-Link TL-SX3008F 8-Port 10G SFP+ Managed Switch', 'brand' => 'TP-Link', 'model' => 'TL-SX3008F', 'sku' => 'TPL-SX3008F',
                'category' => '10g-switches', 'price' => 39000, 'stock' => 8,
                'port_count' => 8, 'sfp_plus_ports' => 8, 'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['data-centre', 'enterprise'],
            ],

            // Ubiquiti UniFi
            [
                'name' => 'Ubiquiti UniFi Switch Lite 8 PoE', 'brand' => 'Ubiquiti', 'model' => 'USW-Lite-8-PoE', 'sku' => 'UBI-USWLITE8POE',
                'category' => '8-port-poe-switches', 'price' => 16500, 'stock' => 22,
                'port_count' => 8, 'rj45_ports' => 8, 'poe_ports' => 4, 'poe_standard' => 'at', 'poe_budget' => 52, 'poe_max_per_port' => 30,
                'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['wifi', 'office', 'small-business'],
            ],
            [
                'name' => 'Ubiquiti UniFi Switch 24 PoE', 'brand' => 'Ubiquiti', 'model' => 'USW-24-PoE', 'sku' => 'UBI-USW24POE',
                'category' => '24-port-poe-switches', 'price' => 65000, 'stock' => 14,
                'port_count' => 26, 'rj45_ports' => 24, 'sfp_ports' => 2, 'poe_ports' => 16, 'poe_standard' => 'at', 'poe_budget' => 95, 'poe_max_per_port' => 30,
                'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['wifi', 'office', 'enterprise'],
            ],
            [
                'name' => 'Ubiquiti UniFi Switch Pro 24 PoE', 'brand' => 'Ubiquiti', 'model' => 'USW-Pro-24-PoE', 'sku' => 'UBI-USWPRO24POE',
                'category' => 'poe-plus-switches', 'price' => 98000, 'stock' => 9,
                'port_count' => 26, 'rj45_ports' => 24, 'sfp_plus_ports' => 2, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 400, 'poe_max_per_port' => 30,
                'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['wifi', 'enterprise', 'cctv'],
            ],
            [
                'name' => 'Ubiquiti UniFi Switch Pro 48', 'brand' => 'Ubiquiti', 'model' => 'USW-Pro-48', 'sku' => 'UBI-USWPRO48',
                'category' => '48-port-switches', 'price' => 118000, 'stock' => 7,
                'port_count' => 52, 'rj45_ports' => 48, 'sfp_plus_ports' => 4, 'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['office', 'enterprise'],
            ],
            [
                'name' => 'Ubiquiti UniFi Switch Aggregation 8-Port 10G', 'brand' => 'Ubiquiti', 'model' => 'USW-Aggregation', 'sku' => 'UBI-USWAGG',
                'category' => 'aggregation-switches', 'price' => 64000, 'stock' => 6,
                'port_count' => 8, 'sfp_plus_ports' => 8, 'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['data-centre', 'enterprise', 'isp'],
            ],

            // MikroTik
            [
                'name' => 'MikroTik CRS112-8G-4S-IN 8-Port Gigabit Smart Switch', 'brand' => 'MikroTik', 'model' => 'CRS112-8G-4S-IN', 'sku' => 'MIK-CRS112',
                'category' => 'smart-managed-switches', 'price' => 17500, 'stock' => 15,
                'port_count' => 12, 'rj45_ports' => 8, 'sfp_ports' => 4, 'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['office', 'small-business', 'isp'],
            ],
            [
                'name' => 'MikroTik CRS326-24G-2S+IN 24-Port Gigabit Switch', 'brand' => 'MikroTik', 'model' => 'CRS326-24G-2S+IN', 'sku' => 'MIK-CRS326',
                'category' => '24-port-switches', 'price' => 35000, 'stock' => 12,
                'port_count' => 26, 'rj45_ports' => 24, 'sfp_plus_ports' => 2, 'management_type' => 'managed', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['office', 'isp'],
            ],
            [
                'name' => 'MikroTik CRS328-24P-4S+RM 24-Port PoE+ Switch', 'brand' => 'MikroTik', 'model' => 'CRS328-24P-4S+RM', 'sku' => 'MIK-CRS32824P',
                'category' => '24-port-poe-switches', 'price' => 65000, 'stock' => 10,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_plus_ports' => 4, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 450, 'poe_max_per_port' => 30,
                'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['cctv', 'wifi', 'isp', 'enterprise'],
            ],
            [
                'name' => 'MikroTik RB260GS 5-Port Gigabit SFP Smart Switch', 'brand' => 'MikroTik', 'model' => 'RB260GS', 'sku' => 'MIK-RB260GS',
                'category' => 'sfp-switches', 'price' => 6000, 'stock' => 18,
                'port_count' => 5, 'rj45_ports' => 5, 'sfp_ports' => 1, 'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'desktop' => true, 'cooling' => 'fanless', 'applications' => ['small-business', 'office'],
            ],
            [
                'name' => 'MikroTik CSS610-8G-2S+IN 8-Port Gigabit SFP+ Switch', 'brand' => 'MikroTik', 'model' => 'CSS610-8G-2S+IN', 'sku' => 'MIK-CSS610',
                'category' => 'sfp-plus-switches', 'price' => 29000, 'stock' => 11,
                'port_count' => 10, 'rj45_ports' => 8, 'sfp_plus_ports' => 2, 'management_type' => 'managed', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless', 'applications' => ['office', 'isp'],
            ],

            // D-Link
            [
                'name' => 'D-Link DGS-1008D 8-Port Gigabit Switch', 'brand' => 'D-Link', 'model' => 'DGS-1008D', 'sku' => 'DLK-DGS1008D',
                'category' => '8-port-switches', 'price' => 2600, 'stock' => 30,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => 'Lifetime', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'D-Link DGS-1024D 24-Port Gigabit Switch', 'brand' => 'D-Link', 'model' => 'DGS-1024D', 'sku' => 'DLK-DGS1024D',
                'category' => '24-port-switches', 'price' => 15500, 'stock' => 20,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'D-Link DES-1016D 16-Port Fast Ethernet Switch', 'brand' => 'D-Link', 'model' => 'DES-1016D', 'sku' => 'DLK-DES1016D',
                'category' => 'fast-ethernet-switches', 'price' => 3800, 'stock' => 15,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],
            [
                'name' => 'D-Link DGS-1100-08 8-Port Gigabit Smart Switch', 'brand' => 'D-Link', 'model' => 'DGS-1100-08', 'sku' => 'DLK-DGS110008',
                'category' => 'smart-managed-switches', 'price' => 7500, 'stock' => 16,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'smart', 'port_speed' => '1g',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless', 'warranty' => 'Lifetime', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'D-Link DGS-1210-28P 24-Port Gigabit PoE+ Smart Switch', 'brand' => 'D-Link', 'model' => 'DGS-1210-28P', 'sku' => 'DLK-DGS121028P',
                'category' => '24-port-poe-switches', 'price' => 35000, 'stock' => 9,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 193, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime',
                'applications' => ['cctv', 'wifi', 'office'],
            ],

            // Tenda
            [
                'name' => 'Tenda TEG1024D 24-Port Gigabit Switch', 'brand' => 'Tenda', 'model' => 'TEG1024D', 'sku' => 'TND-TEG1024D',
                'category' => '24-port-switches', 'price' => 13500, 'stock' => 22,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['office', 'small-business', 'cyber-cafe'],
            ],
            [
                'name' => 'Tenda TEG1109P-8-102W 9-Port Gigabit PoE+ Switch', 'brand' => 'Tenda', 'model' => 'TEG1109P-8-102W', 'sku' => 'TND-TEG1109P',
                'category' => '8-port-poe-switches', 'price' => 8900, 'compare_at_price' => 10200, 'stock' => 18,
                'port_count' => 9, 'rj45_ports' => 9, 'poe_ports' => 8, 'poe_standard' => 'at', 'poe_budget' => 110, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['cctv', 'wifi', 'home'],
            ],
            [
                'name' => 'Tenda SG105 5-Port Gigabit Switch', 'brand' => 'Tenda', 'model' => 'SG105', 'sku' => 'TND-SG105',
                'category' => '5-port-switches', 'price' => 1600, 'stock' => 45,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home'],
            ],

            // Netis
            [
                'name' => 'Netis ST3108GS 8-Port Gigabit Switch', 'brand' => 'Netis', 'model' => 'ST3108GS', 'sku' => 'NTS-ST3108GS',
                'category' => '8-port-switches', 'price' => 2400, 'stock' => 28,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],

            // Ruijie / Reyee
            [
                'name' => 'Ruijie Reyee RG-ES209GS 8-Port Gigabit Smart Switch', 'brand' => 'Ruijie', 'model' => 'RG-ES209GS', 'sku' => 'RUI-RGES209GS',
                'category' => 'smart-managed-switches', 'price' => 8900, 'stock' => 14,
                'port_count' => 9, 'rj45_ports' => 8, 'sfp_ports' => 1, 'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['office', 'small-business', 'hotel'],
            ],

            // Cisco
            [
                'name' => 'Cisco SG110-16HP 16-Port Gigabit PoE Switch', 'brand' => 'Cisco', 'model' => 'SG110-16HP', 'sku' => 'CSC-SG11016HP',
                'category' => '16-port-poe-switches', 'price' => 46000, 'stock' => 8,
                'port_count' => 16, 'rj45_ports' => 16, 'poe_ports' => 8, 'poe_standard' => 'at', 'poe_budget' => 110, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Limited Lifetime',
                'applications' => ['cctv', 'office', 'enterprise'],
            ],
            [
                'name' => 'Cisco CBS250-24P-4G 24-Port Gigabit PoE+ Smart Switch', 'brand' => 'Cisco', 'model' => 'CBS250-24P-4G', 'sku' => 'CSC-CBS25024P',
                'category' => '24-port-poe-switches', 'price' => 72000, 'stock' => 6,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 195, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Limited Lifetime',
                'applications' => ['cctv', 'enterprise', 'office'],
            ],

            // Huawei
            [
                'name' => 'Huawei S1730S-S24T4S-A1 24-Port Gigabit Smart Switch', 'brand' => 'Huawei', 'model' => 'S1730S-S24T4S-A1', 'sku' => 'HUA-S1730S',
                'category' => '24-port-switches', 'price' => 32000, 'stock' => 10,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'management_type' => 'smart', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['office', 'enterprise'],
            ],

            // Hikvision
            [
                'name' => 'Hikvision DS-3E0518P-E 16-Port Gigabit PoE Switch', 'brand' => 'Hikvision', 'model' => 'DS-3E0518P-E', 'sku' => 'HIK-DS3E0518P',
                'category' => '16-port-poe-switches', 'price' => 28000, 'stock' => 13,
                'port_count' => 18, 'rj45_ports' => 16, 'sfp_ports' => 2, 'poe_ports' => 16, 'poe_standard' => 'at', 'poe_budget' => 230, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['cctv'],
            ],

            // Dahua
            [
                'name' => 'Dahua DH-PFS3010-8ET-96 8-Port PoE Switch', 'brand' => 'Dahua', 'model' => 'DH-PFS3010-8ET-96', 'sku' => 'DAH-PFS30108ET',
                'category' => '8-port-poe-switches', 'price' => 12000, 'stock' => 17,
                'port_count' => 10, 'rj45_ports' => 10, 'poe_ports' => 8, 'poe_standard' => 'at', 'poe_budget' => 96, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['cctv'],
            ],

            // Grandstream
            [
                'name' => 'Grandstream GWN7802P 8-Port Gigabit PoE+ Managed Switch', 'brand' => 'Grandstream', 'model' => 'GWN7802P', 'sku' => 'GST-GWN7802P',
                'category' => '8-port-poe-switches', 'price' => 24000, 'stock' => 11,
                'port_count' => 10, 'rj45_ports' => 8, 'sfp_ports' => 2, 'poe_ports' => 8, 'poe_standard' => 'at', 'poe_budget' => 120, 'poe_max_per_port' => 30,
                'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'desktop' => true, 'cooling' => 'fanless',
                'applications' => ['voip', 'wifi', 'office'],
            ],

            // Aruba
            [
                'name' => 'Aruba Instant On 1930 24G 24-Port Gigabit Managed Switch', 'brand' => 'Aruba', 'model' => 'Aruba Instant On 1930 24G', 'sku' => 'ARU-193024G',
                'category' => 'managed-switches', 'price' => 38000, 'stock' => 9,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Limited Lifetime',
                'applications' => ['office', 'enterprise'],
            ],

            // Mercusys
            [
                'name' => 'Mercusys MS108G 8-Port Gigabit Switch', 'brand' => 'Mercusys', 'model' => 'MS108G', 'sku' => 'MER-MS108G',
                'category' => '8-port-switches', 'price' => 2000, 'stock' => 32,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],

            // Zyxel
            [
                'name' => 'Zyxel GS1900-24HP 24-Port Gigabit PoE+ Smart Switch', 'brand' => 'Zyxel', 'model' => 'GS1900-24HP', 'sku' => 'ZYX-GS190024HP',
                'category' => '24-port-poe-switches', 'price' => 60000, 'stock' => 7,
                'port_count' => 26, 'rj45_ports' => 24, 'sfp_ports' => 2, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 170, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['cctv', 'wifi', 'office'],
            ],

            // CTC Solutions catalogue additions

            // TP-Link (CTC)
            [
                'name' => 'TP-Link TL-SF1008D 8-Port 10/100Mbps Desktop Switch', 'brand' => 'TP-Link', 'model' => 'TL-SF1008D', 'sku' => 'TPL-SF1008D',
                'category' => '8-port-switches', 'price' => 1100, 'stock' => 40,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => '3 Years', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SG1008D 8-Port Gigabit Desktop Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1008D', 'sku' => 'TPL-SG1008D',
                'category' => '8-port-switches', 'price' => 2500, 'stock' => 35,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => '3 Years', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SF1005D 5-Port 10/100Mbps Desktop Switch', 'brand' => 'TP-Link', 'model' => 'TL-SF1005D', 'sku' => 'TPL-SF1005D',
                'category' => '5-port-switches', 'price' => 900, 'stock' => 45,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1005D 5-Port Gigabit Desktop Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1005D', 'sku' => 'TPL-SG1005D',
                'category' => '5-port-switches', 'price' => 1500, 'stock' => 40,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => '3 Years', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link LS1005G 5-Port Gigabit Desktop Switch', 'brand' => 'TP-Link', 'model' => 'LS1005G', 'sku' => 'TPL-LS1005G',
                'category' => '5-port-switches', 'price' => 2000, 'stock' => 40,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1016D 16-Port Gigabit Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1016D', 'sku' => 'TPL-SG1016D',
                'category' => '16-port-switches', 'price' => 7500, 'stock' => 25,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => '3 Years', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1016 16-Port Gigabit Rackmount Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1016', 'sku' => 'TPL-SG1016',
                'category' => '16-port-switches', 'price' => 7500, 'stock' => 25,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => '3 Years', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SF1016D 16-Port 10/100Mbps Desktop Switch', 'brand' => 'TP-Link', 'model' => 'TL-SF1016D', 'sku' => 'TPL-SF1016D',
                'category' => '16-port-switches', 'price' => 2500, 'stock' => 25,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SG1024D 24-Port Gigabit Rackmount Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1024D', 'sku' => 'TPL-SG1024D',
                'category' => '24-port-switches', 'price' => 10500, 'stock' => 18,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => '3 Years', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SG1024 24-Port Gigabit Rackmount Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG1024', 'sku' => 'TPL-SG1024',
                'category' => '24-port-switches', 'price' => 10000, 'stock' => 18,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => '3 Years', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SF1024D 24-Port Rackmount Switch', 'brand' => 'TP-Link', 'model' => 'TL-SF1024D', 'sku' => 'TPL-SF1024D',
                'category' => '24-port-switches', 'price' => 6000, 'stock' => 20,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'TP-Link TL-SF1048 48-Port 10/100Mbps Rackmount Switch', 'brand' => 'TP-Link', 'model' => 'TL-SF1048', 'sku' => 'TPL-SF1048',
                'category' => '48-port-switches', 'price' => 12500, 'stock' => 10,
                'port_count' => 48, 'rj45_ports' => 48, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['office'],
            ],
            [
                'name' => 'TP-Link SG1048 48-Port Gigabit Ethernet Switch', 'brand' => 'TP-Link', 'model' => 'SG1048', 'sku' => 'TPL-SG1048',
                'category' => '48-port-switches', 'price' => 25000, 'stock' => 10,
                'port_count' => 48, 'rj45_ports' => 48, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'active', 'warranty' => '3 Years', 'applications' => ['office', 'enterprise'],
            ],
            [
                'name' => 'TP-Link TL-SG2210MP JetStream 10-Port Gigabit Smart PoE+ Switch', 'brand' => 'TP-Link', 'model' => 'TL-SG2210MP', 'sku' => 'TPL-SG2210MP',
                'category' => '8-port-poe-switches', 'price' => 13000, 'stock' => 12,
                'port_count' => 10, 'rj45_ports' => 8, 'sfp_ports' => 2, 'poe_ports' => 8, 'poe_standard' => 'at', 'poe_budget' => 61, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'active',
                'applications' => ['cctv', 'wifi', 'office'],
            ],
            [
                'name' => 'TP-Link TL-SG3452XP 48-Port Gigabit PoE+ Switch with 10G SFP+ Uplinks', 'brand' => 'TP-Link', 'model' => 'TL-SG3452XP', 'sku' => 'TPL-SG3452XP',
                'category' => '48-port-poe-switches', 'price' => 83000, 'stock' => 8,
                'port_count' => 52, 'rj45_ports' => 48, 'sfp_plus_ports' => 4, 'poe_ports' => 48, 'poe_standard' => 'at', 'poe_budget' => 384, 'poe_max_per_port' => 30,
                'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['cctv', 'wifi', 'enterprise'],
            ],

            // Tenda (CTC)
            [
                'name' => 'Tenda S105 5-Port Ethernet Switch', 'brand' => 'Tenda', 'model' => 'S105', 'sku' => 'TND-S105',
                'category' => '5-port-switches', 'price' => 750, 'stock' => 50,
                'port_count' => 5, 'rj45_ports' => 5, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home'],
            ],
            [
                'name' => 'Tenda S108 8-Port Ethernet Switch', 'brand' => 'Tenda', 'model' => 'S108', 'sku' => 'TND-S108',
                'category' => '8-port-switches', 'price' => 850, 'stock' => 50,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],
            [
                'name' => 'Tenda S16 16-Port 10/100Mbps Desktop Switch', 'brand' => 'Tenda', 'model' => 'S16', 'sku' => 'TND-S16',
                'category' => '16-port-switches', 'price' => 2600, 'stock' => 30,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],

            // D-Link (CTC)
            [
                'name' => 'D-Link DGS-F108 8-Port Gigabit Desktop Switch', 'brand' => 'D-Link', 'model' => 'DGS-F108', 'sku' => 'DLK-DGSF108',
                'category' => '8-port-switches', 'price' => 3500, 'stock' => 25,
                'port_count' => 8, 'rj45_ports' => 8, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'desktop' => true, 'cooling' => 'fanless', 'warranty' => 'Lifetime', 'applications' => ['home', 'small-business', 'office'],
            ],
            [
                'name' => 'D-Link DGS-1016C 16-Port Gigabit Unmanaged Switch', 'brand' => 'D-Link', 'model' => 'DGS-1016C', 'sku' => 'DLK-DGS1016C',
                'category' => '16-port-switches', 'price' => 5500, 'stock' => 20,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '1g',
                'rackmount' => true, 'cooling' => 'fanless', 'warranty' => 'Lifetime', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'D-Link DES-1016A 16-Port 10/100Mbps Desktop Switch', 'brand' => 'D-Link', 'model' => 'DES-1016A', 'sku' => 'DLK-DES1016A',
                'category' => '16-port-switches', 'price' => 2800, 'stock' => 20,
                'port_count' => 16, 'rj45_ports' => 16, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'desktop' => true, 'cooling' => 'fanless', 'applications' => ['home', 'small-business'],
            ],
            [
                'name' => 'D-Link DES-1024D 24-Port Desktop Switch', 'brand' => 'D-Link', 'model' => 'DES-1024D', 'sku' => 'DLK-DES1024D',
                'category' => '24-port-switches', 'price' => 5000, 'stock' => 15,
                'port_count' => 24, 'rj45_ports' => 24, 'management_type' => 'unmanaged', 'port_speed' => '100m',
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['office', 'small-business'],
            ],
            [
                'name' => 'D-Link DGS-F1210-18PS-E 16-Port Gigabit PoE+ Smart Switch', 'brand' => 'D-Link', 'model' => 'DGS-F1210-18PS-E', 'sku' => 'DLK-DGSF121018PS',
                'category' => '16-port-poe-switches', 'price' => 22500, 'stock' => 9,
                'port_count' => 18, 'rj45_ports' => 16, 'sfp_ports' => 2, 'poe_ports' => 16, 'poe_standard' => 'at', 'poe_budget' => 150, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime',
                'applications' => ['cctv', 'wifi', 'office'],
            ],
            [
                'name' => 'D-Link DGS-F1210-26PS-E 24-Port Gigabit PoE+ Smart Switch', 'brand' => 'D-Link', 'model' => 'DGS-F1210-26PS-E', 'sku' => 'DLK-DGSF121026PS',
                'category' => '24-port-poe-switches', 'price' => 32000, 'stock' => 8,
                'port_count' => 26, 'rj45_ports' => 24, 'sfp_ports' => 2, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 250, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime',
                'applications' => ['cctv', 'wifi', 'office'],
            ],
            [
                'name' => 'D-Link DGS-1210-28MP 28-Port Gigabit PoE+ Smart Switch', 'brand' => 'D-Link', 'model' => 'DGS-1210-28MP', 'sku' => 'DLK-DGS121028MP',
                'category' => '24-port-poe-switches', 'price' => 38000, 'stock' => 7,
                'port_count' => 28, 'rj45_ports' => 24, 'sfp_ports' => 4, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 193, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime',
                'applications' => ['cctv', 'wifi', 'office'],
            ],
            [
                'name' => 'D-Link DGS-1210-52MP 52-Port Managed PoE+ Switch', 'brand' => 'D-Link', 'model' => 'DGS-1210-52MP', 'sku' => 'DLK-DGS121052MP',
                'category' => '48-port-poe-switches', 'price' => 65000, 'stock' => 6,
                'port_count' => 52, 'rj45_ports' => 48, 'sfp_ports' => 4, 'poe_ports' => 48, 'poe_standard' => 'at', 'poe_budget' => 370, 'poe_max_per_port' => 30,
                'management_type' => 'smart', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'snmp' => true, 'rackmount' => true, 'cooling' => 'active', 'warranty' => 'Lifetime',
                'applications' => ['cctv', 'wifi', 'enterprise'],
            ],

            // MikroTik (CTC)
            [
                'name' => 'MikroTik CRS305-1G-4S+IN 5-Port 10G SFP+ Switch', 'brand' => 'MikroTik', 'model' => 'CRS305-1G-4S+IN', 'sku' => 'MIK-CRS305',
                'category' => 'sfp-plus-switches', 'price' => 18000, 'stock' => 8,
                'port_count' => 5, 'rj45_ports' => 1, 'sfp_plus_ports' => 4, 'management_type' => 'managed', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'desktop' => true, 'cooling' => 'fanless', 'applications' => ['data-centre', 'isp', 'enterprise'],
            ],
            [
                'name' => 'MikroTik CRS309-1G-8S+IN 9-Port 10G SFP+ Switch', 'brand' => 'MikroTik', 'model' => 'CRS309-1G-8S+IN', 'sku' => 'MIK-CRS309',
                'category' => 'sfp-plus-switches', 'price' => 38000, 'stock' => 7,
                'port_count' => 9, 'rj45_ports' => 1, 'sfp_plus_ports' => 8, 'management_type' => 'managed', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'desktop' => true, 'cooling' => 'fanless', 'applications' => ['data-centre', 'isp', 'enterprise'],
            ],
            [
                'name' => 'MikroTik CRS312-4C+8XG-RM 12-Port 10G SFP+ Switch', 'brand' => 'MikroTik', 'model' => 'CRS312-4C+8XG-RM', 'sku' => 'MIK-CRS312',
                'category' => '10g-switches', 'price' => 83000, 'stock' => 5,
                'port_count' => 12, 'sfp_plus_ports' => 12, 'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['data-centre', 'isp', 'enterprise'],
            ],
            [
                'name' => 'MikroTik CRS317-1G-16S+RM 17-Port 10G SFP+ Switch', 'brand' => 'MikroTik', 'model' => 'CRS317-1G-16S+RM', 'sku' => 'MIK-CRS317',
                'category' => '10g-switches', 'price' => 62000, 'stock' => 5,
                'port_count' => 17, 'rj45_ports' => 1, 'sfp_plus_ports' => 16, 'management_type' => 'managed', 'layer' => 'l2', 'port_speed' => '10g', 'uplink_type' => 'sfp+',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'lacp' => true, 'snmp' => true, 'acl' => true,
                'rackmount' => true, 'cooling' => 'active', 'applications' => ['data-centre', 'isp', 'enterprise'],
            ],

            // Ubiquiti (CTC)
            [
                'name' => 'Ubiquiti UniFi USW-16-PoE 16-Port Gigabit PoE Switch', 'brand' => 'Ubiquiti', 'model' => 'USW-16-PoE', 'sku' => 'UBI-USW16POE',
                'category' => '16-port-poe-switches', 'price' => 55000, 'stock' => 6,
                'port_count' => 18, 'rj45_ports' => 16, 'sfp_ports' => 2, 'poe_ports' => 16, 'poe_standard' => 'at', 'poe_budget' => 42, 'poe_max_per_port' => 30,
                'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['wifi', 'cctv', 'office'],
            ],
            [
                'name' => 'Ubiquiti UniFi USW-48-PoE 48-Port Gigabit PoE Switch', 'brand' => 'Ubiquiti', 'model' => 'USW-48-PoE', 'sku' => 'UBI-USW48POE',
                'category' => '48-port-poe-switches', 'price' => 90000, 'stock' => 5,
                'port_count' => 52, 'rj45_ports' => 48, 'sfp_ports' => 4, 'poe_ports' => 48, 'poe_standard' => 'at', 'poe_budget' => 195, 'poe_max_per_port' => 30,
                'management_type' => 'cloud', 'layer' => 'l2', 'port_speed' => '1g', 'uplink_type' => 'sfp',
                'vlan_support' => true, 'qos' => true, 'stp' => true, 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['wifi', 'enterprise', 'cctv'],
            ],

            // Dahua (CTC)
            [
                'name' => 'Dahua DH-CS4220-16GT-135 16-Port Gigabit PoE Switch', 'brand' => 'Dahua', 'model' => 'DH-CS4220-16GT-135', 'sku' => 'DAH-CS422016GT',
                'category' => '16-port-poe-switches', 'price' => 16000, 'stock' => 10,
                'port_count' => 16, 'rj45_ports' => 16, 'poe_ports' => 16, 'poe_standard' => 'at', 'poe_budget' => 135, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['cctv'],
            ],
            [
                'name' => 'Dahua DH-CS4226-24ET-240 24-Port Gigabit PoE Switch', 'brand' => 'Dahua', 'model' => 'DH-CS4226-24ET-240', 'sku' => 'DAH-CS422624ET',
                'category' => '24-port-poe-switches', 'price' => 20000, 'stock' => 10,
                'port_count' => 24, 'rj45_ports' => 24, 'poe_ports' => 24, 'poe_standard' => 'at', 'poe_budget' => 240, 'poe_max_per_port' => 30,
                'management_type' => 'unmanaged', 'port_speed' => '1g', 'rackmount' => true, 'cooling' => 'active',
                'applications' => ['cctv'],
            ],
        ];
    }
}
