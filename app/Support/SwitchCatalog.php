<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Central definition of the network-switch catalogue: category tree, brands,
 * ports, speeds, solutions, filter options, category attribute rules, the
 * guided Switch Finder and navigation structure.
 *
 * Products belong to a single primary category but are surfaced dynamically in
 * every relevant category through the attribute rules in this class, so no
 * product is ever duplicated in the database.
 */
class SwitchCatalog
{
    public const TYPE_SHOP = 'shop';

    public const TYPE_PORTS = 'ports';

    public const TYPE_BRANDS = 'brands';

    public const TYPE_SOLUTIONS = 'solutions';

    /**
     * Brand slug => display name.
     *
     * @return array<string, string>
     */
    public static function brands(): array
    {
        return [
            'tp-link' => 'TP-Link',
            'ubiquiti' => 'Ubiquiti',
            'mikrotik' => 'MikroTik',
            'd-link' => 'D-Link',
            'tenda' => 'Tenda',
            'netis' => 'Netis',
            'ruijie' => 'Ruijie',
            'cisco' => 'Cisco',
            'huawei' => 'Huawei',
            'hikvision' => 'Hikvision',
            'dahua' => 'Dahua',
            'grandstream' => 'Grandstream',
            'aruba' => 'Aruba',
            'mercusys' => 'Mercusys',
            'zyxel' => 'Zyxel',
        ];
    }

    /**
     * Accepted lowercased spellings per brand for robust matching.
     *
     * @return array<string, array<int, string>>
     */
    public static function brandAliases(string $brand): array
    {
        return match ($brand) {
            'tp-link' => ['tp-link', 'tp link', 'tplink', 'tp-linksg'],
            'ubiquiti' => ['ubiquiti', 'ubiquiti unifi', 'unifi', 'unifi networks'],
            'mikrotik' => ['mikrotik', 'mikro tik'],
            'd-link' => ['d-link', 'd link', 'dlink'],
            'tenda' => ['tenda'],
            'netis' => ['netis', 'netis systems'],
            'ruijie' => ['ruijie', 'reyee', 'ruijie reyee', 'ruijie networks'],
            'cisco' => ['cisco', 'cisco catalyst', 'cisco small business', 'linksys business'],
            'huawei' => ['huawei'],
            'hikvision' => ['hikvision', 'hik'],
            'dahua' => ['dahua'],
            'grandstream' => ['grandstream'],
            'aruba' => ['aruba', 'hpe aruba', 'aruba networks'],
            'mercusys' => ['mercusys'],
            'zyxel' => ['zyxel', 'zyxel networks'],
            default => [str_replace('-', ' ', $brand)],
        };
    }

    /**
     * @return array<int, int>
     */
    public static function ports(): array
    {
        return [4, 5, 8, 10, 16, 24, 28, 48, 52];
    }

    /**
     * @return array<string, string>
     */
    public static function speeds(): array
    {
        return [
            '100m' => 'Fast Ethernet (100 Mbps)',
            '1g' => 'Gigabit (1 Gbps)',
            '2.5g' => '2.5 Gigabit',
            '5g' => '5 Gigabit',
            '10g' => '10 Gigabit',
            '25g' => '25 Gigabit',
            '40g' => '40 Gigabit',
            '100g' => '100 Gigabit',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function managementTypes(): array
    {
        return [
            'unmanaged' => 'Unmanaged',
            'smart' => 'Smart Managed',
            'managed' => 'Managed',
            'cloud' => 'Cloud Managed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function poeStandards(): array
    {
        return [
            'af' => 'PoE (802.3af)',
            'at' => 'PoE+ (802.3at)',
            'bt' => 'PoE++ (802.3bt)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function uplinks(): array
    {
        return [
            'rj45' => 'RJ45',
            'sfp' => 'SFP (1G)',
            'sfp+' => 'SFP+ (10G)',
            'sfp28' => 'SFP28 (25G)',
            'qsfp+' => 'QSFP+ (40G)',
            'qsfp28' => 'QSFP28 (100G)',
        ];
    }

    /**
     * @return array<string, array{min: int|null, max: int|null, label: string}>
     */
    public static function poeBudgetRanges(): array
    {
        return [
            'under-60' => ['min' => null, 'max' => 59, 'label' => 'Under 60W'],
            '60-120' => ['min' => 60, 'max' => 120, 'label' => '60W - 120W'],
            '121-250' => ['min' => 121, 'max' => 250, 'label' => '121W - 250W'],
            '251-500' => ['min' => 251, 'max' => 500, 'label' => '251W - 500W'],
            'above-500' => ['min' => 501, 'max' => null, 'label' => 'Above 500W'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function installationTypes(): array
    {
        return [
            'desktop' => 'Desktop',
            'rackmount' => 'Rackmount',
            'din_rail' => 'DIN Rail',
            'outdoor' => 'Outdoor',
        ];
    }

    /**
     * Application slug => display name.
     *
     * @return array<string, string>
     */
    public static function applications(): array
    {
        return [
            'cctv' => 'CCTV / IP Cameras',
            'wifi' => 'WiFi Access Points',
            'office' => 'Office Networks',
            'isp' => 'ISP Networks',
            'hotel' => 'Hotel Networks',
            'school' => 'School Networks',
            'enterprise' => 'Enterprise Networks',
            'data-centre' => 'Data Centre',
            'cyber-cafe' => 'Cyber Cafe',
            'small-business' => 'Small Business',
            'home' => 'Home Networks',
            'voip' => 'VoIP Phones',
            'access-control' => 'Access Control',
        ];
    }

    /**
     * Brand subcategories for brands with large product ranges.
     *
     * @return array<string, array{name: string, parent: string, rule: array<string, mixed>}>
     */
    public static function brandSubcategories(): array
    {
        $brand = fn (string $slug): array => ['brand' => $slug];

        return [
            // TP-Link
            'tp-link-unmanaged-switches' => ['name' => 'TP-Link Unmanaged Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['management' => 'unmanaged']],
            'tp-link-poe-switches' => ['name' => 'TP-Link PoE Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['poe' => 'yes']],
            'tp-link-managed-switches' => ['name' => 'TP-Link Managed Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['management' => ['managed', 'cloud']]],
            'tp-link-omada-switches' => ['name' => 'TP-Link Omada Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['keyword' => 'omada']],
            'tp-link-gigabit-switches' => ['name' => 'TP-Link Gigabit Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['speed' => '1g']],
            'tp-link-2-5g-switches' => ['name' => 'TP-Link 2.5G Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['speed' => '2.5g']],
            'tp-link-10g-switches' => ['name' => 'TP-Link 10G Switches', 'parent' => 'tp-link-switches', 'rule' => $brand('tp-link') + ['speed' => '10g']],

            // MikroTik
            'mikrotik-css-switches' => ['name' => 'MikroTik CSS Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['keyword' => 'css']],
            'mikrotik-crs-switches' => ['name' => 'MikroTik CRS Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['keyword' => 'crs']],
            'mikrotik-poe-switches' => ['name' => 'MikroTik PoE Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['poe' => 'yes']],
            'mikrotik-sfp-switches' => ['name' => 'MikroTik SFP Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['sfp_ports' => true]],
            'mikrotik-sfp-plus-switches' => ['name' => 'MikroTik SFP+ Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['sfp_plus_ports' => true]],
            'mikrotik-10g-switches' => ['name' => 'MikroTik 10G Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['speed' => '10g']],
            'mikrotik-cloud-router-switches' => ['name' => 'MikroTik Cloud Router Switches', 'parent' => 'mikrotik-switches', 'rule' => $brand('mikrotik') + ['keyword' => 'ccr']],

            // Ubiquiti / UniFi
            'unifi-lite-switches' => ['name' => 'UniFi Lite Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'lite']],
            'unifi-standard-switches' => ['name' => 'UniFi Standard Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'usw']],
            'unifi-poe-switches' => ['name' => 'UniFi PoE Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['poe' => 'yes']],
            'unifi-professional-switches' => ['name' => 'UniFi Professional Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'pro']],
            'unifi-pro-max-switches' => ['name' => 'UniFi Pro Max Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'pro max']],
            'unifi-enterprise-switches' => ['name' => 'UniFi Enterprise Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'enterprise']],
            'unifi-aggregation-switches' => ['name' => 'UniFi Aggregation Switches', 'parent' => 'ubiquiti-switches', 'rule' => $brand('ubiquiti') + ['keyword' => 'aggregation']],

            // D-Link
            'd-link-unmanaged-switches' => ['name' => 'D-Link Unmanaged Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['management' => 'unmanaged']],
            'd-link-poe-switches' => ['name' => 'D-Link PoE Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['poe' => 'yes']],
            'd-link-smart-managed-switches' => ['name' => 'D-Link Smart Managed Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['management' => 'smart']],
            'd-link-managed-switches' => ['name' => 'D-Link Managed Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['management' => ['managed', 'cloud']]],
            'd-link-gigabit-switches' => ['name' => 'D-Link Gigabit Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['speed' => '1g']],
            'd-link-multi-gigabit-switches' => ['name' => 'D-Link Multi-Gigabit Switches', 'parent' => 'd-link-switches', 'rule' => $brand('d-link') + ['speed' => ['2.5g', '5g', '10g']]],

            // Tenda
            'tenda-unmanaged-switches' => ['name' => 'Tenda Unmanaged Switches', 'parent' => 'tenda-switches', 'rule' => $brand('tenda') + ['management' => 'unmanaged']],
            'tenda-gigabit-switches' => ['name' => 'Tenda Gigabit Switches', 'parent' => 'tenda-switches', 'rule' => $brand('tenda') + ['speed' => '1g']],
            'tenda-poe-switches' => ['name' => 'Tenda PoE Switches', 'parent' => 'tenda-switches', 'rule' => $brand('tenda') + ['poe' => 'yes']],
            'tenda-managed-switches' => ['name' => 'Tenda Managed Switches', 'parent' => 'tenda-switches', 'rule' => $brand('tenda') + ['management' => ['managed', 'smart', 'cloud']]],
        ];
    }

    /**
     * Full category definition map: slug => config.
     *
     * @return array<string, array{name: string, meta: string, description: string, type: string, parent: ?string, rule: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        $definitions = [];

        $shop = function (string $slug, string $name, string $meta, string $description, ?string $parent, array $rule = []) use (&$definitions): void {
            $definitions[$slug] = [
                'name' => $name,
                'meta' => $meta,
                'description' => $description,
                'type' => self::TYPE_SHOP,
                'parent' => $parent,
                'rule' => $rule,
            ];
        };

        $shop(
            'network-switches',
            'Network Switches in Kenya',
            'Shop network switches in Kenya - PoE, managed, unmanaged, gigabit, multi-gigabit and fiber switches from TP-Link, Ubiquiti, MikroTik, D-Link and more.',
            '<p>Kenya\'s specialist network switch store. Compare PoE, managed, unmanaged, gigabit, multi-gigabit and fiber switches by port count, brand, speed and application.</p>',
            null
        );

        // By type
        $shop('unmanaged-switches', 'Unmanaged Switches', 'Shop unmanaged network switches in Kenya - plug and play switches for home, office and small business networks.', '<p>Unmanaged switches are plug-and-play, requiring no configuration. Ideal for homes, small offices and simple network expansion.</p>', 'network-switches', ['management' => 'unmanaged']);
        $shop('smart-managed-switches', 'Smart Managed Switches', 'Buy smart managed switches in Kenya with basic VLAN, QoS and web management at an affordable price.', '<p>Smart managed switches offer essential management features such as VLANs and QoS through an easy web interface.</p>', 'network-switches', ['management' => 'smart']);
        $shop('managed-switches', 'Managed Switches', 'Shop managed network switches in Kenya for VLAN, QoS, SNMP and full network control.', '<p>Managed switches give you full control over VLANs, QoS, security and monitoring for business and enterprise networks.</p>', 'network-switches', ['management' => 'managed']);
        $shop('cloud-managed-switches', 'Cloud Managed Switches', 'Buy cloud managed switches in Kenya - monitor and manage your network from the cloud.', '<p>Cloud managed switches are configured and monitored from a cloud controller, ideal for multi-site deployments.</p>', 'network-switches', ['management' => 'cloud']);
        $shop('layer-2-switches', 'Layer 2 Switches', 'Shop Layer 2 network switches in Kenya for switching, VLAN and access networks.', '<p>Layer 2 switches forward traffic based on MAC addresses and provide VLAN and access-layer switching.</p>', 'network-switches', ['layer' => 'l2']);
        $shop('layer-3-switches', 'Layer 3 Switches', 'Buy Layer 3 switches in Kenya with routing, inter-VLAN routing and static routing support.', '<p>Layer 3 switches add routing capabilities for inter-VLAN routing and more scalable enterprise networks.</p>', 'network-switches', ['layer' => 'l3']);
        $shop('industrial-switches', 'Industrial Switches', 'Shop industrial Ethernet switches in Kenya - rugged DIN rail switches for harsh environments.', '<p>Industrial switches are built for harsh environments with wide temperature ranges and DIN rail mounting.</p>', 'network-switches', ['industrial' => true]);
        $shop('outdoor-switches', 'Outdoor Switches', 'Buy outdoor network switches in Kenya for CCTV, WiFi and outdoor installations.', '<p>Outdoor switches are weatherproof for outdoor CCTV, WiFi and perimeter networking installations.</p>', 'network-switches', ['outdoor' => true]);
        $shop('aggregation-switches', 'Aggregation Switches', 'Shop aggregation switches in Kenya with high-speed SFP+ and fiber uplinks for network cores.', '<p>Aggregation switches provide high-speed fiber uplinks for connecting access switches and building network cores.</p>', 'network-switches', ['uplink' => ['sfp+', 'sfp28', 'qsfp+', 'qsfp28']]);
        $shop('stackable-switches', 'Stackable Switches', 'Buy stackable network switches in Kenya for scalable, high-availability enterprise networks.', '<p>Stackable switches can be combined into a single logical switch for easier management and resilience.</p>', 'network-switches', ['stackable' => true]);

        // By PoE
        $shop('poe-switches', 'PoE Switches', 'Shop PoE network switches in Kenya for CCTV cameras, WiFi access points and VoIP phones.', '<p>PoE switches deliver power and data over a single Ethernet cable to IP cameras, access points and VoIP phones.</p>', 'network-switches', ['poe' => 'yes']);
        $shop('poe-plus-switches', 'PoE+ Switches', 'Buy PoE+ (802.3at) switches in Kenya with up to 30W per port for IP cameras and access points.', '<p>PoE+ switches deliver up to 30W per port for higher-power cameras, access points and phones.</p>', 'network-switches', ['poe_standard' => 'at']);
        $shop('poe-plus-plus-switches', 'PoE++ Switches', 'Shop PoE++ (802.3bt) switches in Kenya with up to 90W per port for high-power devices.', '<p>PoE++ switches deliver up to 90W per port for PTZ cameras, LED lighting and high-power endpoints.</p>', 'network-switches', ['poe_standard' => 'bt']);
        $shop('non-poe-switches', 'Non-PoE Switches', 'Buy non-PoE network switches in Kenya for data-only network expansion.', '<p>Non-PoE switches are for data-only networks where devices are powered separately.</p>', 'network-switches', ['poe' => 'no']);

        // PoE by port count
        foreach ([4, 5, 8, 16, 24, 48] as $ports) {
            $shop(
                $ports.'-port-poe-switches',
                $ports.' Port PoE Switches',
                'Shop '.$ports.' port PoE switches in Kenya for CCTV, WiFi and VoIP with power and data over Ethernet.',
                '<p>'.$ports.' port PoE switches are ideal for powering IP cameras, access points and phones across '.$ports.'-device installations.</p>',
                'poe-switches',
                ['ports' => $ports, 'poe' => 'yes']
            );
        }

        // By speed
        $speedMap = [
            'fast-ethernet-switches' => ['Fast Ethernet Switches', '100m'],
            'gigabit-switches' => ['Gigabit Switches', '1g'],
            '2-5g-switches' => ['2.5G Switches', '2.5g'],
            '5g-switches' => ['5G Switches', '5g'],
            '10g-switches' => ['10G Switches', '10g'],
            '25g-switches' => ['25G Switches', '25g'],
            '40g-switches' => ['40G Switches', '40g'],
            '100g-switches' => ['100G Switches', '100g'],
        ];
        $speedIntro = [
            'fast-ethernet-switches' => 'Fast Ethernet switches provide 100 Mbps connectivity for basic networks and legacy devices.',
            'gigabit-switches' => 'Gigabit switches provide 1000 Mbps connectivity, the standard for modern home and business networks.',
            '2-5g-switches' => '2.5G switches deliver 2.5 Gbps speeds for high-performance workstations and WiFi 6/6E access points.',
            '5g-switches' => '5G switches provide 5 Gbps ports for demanding LAN and NAS workloads.',
            '10g-switches' => '10G switches deliver 10 Gbps connectivity for servers, storage and aggregation.',
            '25g-switches' => '25G switches provide 25 Gbps connectivity for modern data centre and aggregation networks.',
            '40g-switches' => '40G switches deliver 40 Gbps uplinks for high-capacity cores.',
            '100g-switches' => '100G switches provide 100 Gbps connectivity for large data centres and carrier networks.',
        ];
        foreach ($speedMap as $slug => [$label, $speed]) {
            $shop(
                $slug,
                $label,
                'Shop '.Str::lower($label).' in Kenya with current prices and stock availability.',
                '<p>'.$speedIntro[$slug].'</p>',
                'network-switches',
                ['speed' => $speed]
            );
        }

        // Fiber
        $shop('fiber-switches', 'Fiber Switches', 'Shop fiber network switches in Kenya with SFP and SFP+ uplinks for long-distance connectivity.', '<p>Fiber switches use SFP, SFP+ and QSFP uplinks for long-distance, high-speed connectivity between switches and sites.</p>', 'network-switches', ['fiber' => true]);
        $shop('sfp-switches', 'SFP Switches', 'Buy SFP switches in Kenya with 1G fiber uplinks for long-distance connections.', '<p>SFP switches include 1 Gigabit fiber uplinks for linking switches across longer distances.</p>', 'network-switches', ['sfp_ports' => true]);
        $shop('sfp-plus-switches', 'SFP+ Switches', 'Shop SFP+ switches in Kenya with 10G fiber uplinks for high-speed aggregation.', '<p>SFP+ switches include 10 Gigabit fiber uplinks for high-speed links between switches and servers.</p>', 'network-switches', ['sfp_plus_ports' => true]);
        $shop('sfp28-switches', 'SFP28 Switches', 'Buy SFP28 switches in Kenya with 25G uplinks for data centres.', '<p>SFP28 switches provide 25 Gigabit uplinks for modern data centre and aggregation networks.</p>', 'network-switches', ['sfp28_ports' => true]);
        $shop('qsfp-switches', 'QSFP+ Switches', 'Shop QSFP+ switches in Kenya with 40G uplinks for network cores.', '<p>QSFP+ switches provide 40G uplinks for high-capacity network cores and data centres.</p>', 'network-switches', ['qsfp_ports' => true]);
        $shop('qsfp28-switches', 'QSFP28 Switches', 'Shop QSFP28 switches in Kenya with 100G uplinks for data centre cores.', '<p>QSFP28 switches provide 100G uplinks for large data centres and carrier networks.</p>', 'network-switches', ['uplink' => 'qsfp28']);
        $shop('fiber-aggregation-switches', 'Fiber Aggregation Switches', 'Buy fiber aggregation switches in Kenya for connecting access switches over fiber.', '<p>Fiber aggregation switches consolidate multiple access switches over high-speed fiber uplinks.</p>', 'network-switches', ['uplink' => ['sfp+', 'sfp28', 'qsfp+', 'qsfp28']]);

        // Ports
        $definitions['ports'] = [
            'name' => 'Network Switches by Port Count',
            'meta' => 'Shop network switches by port count in Kenya - 5, 8, 16, 24 and 48 port switches from leading brands.',
            'description' => '<p>Choose a network switch by the number of devices you need to connect.</p>',
            'type' => self::TYPE_PORTS,
            'parent' => null,
            'rule' => [],
        ];
        foreach (self::ports() as $ports) {
            $definitions[$ports.'-port-switches'] = [
                'name' => $ports.' Port Network Switches',
                'meta' => 'Shop '.$ports.' port network switches in Kenya - compare brands, prices and stock for '.$ports.'-device networks.',
                'description' => '<p>'.$ports.' port switches connect up to '.$ports.' devices, ideal for the right-sized home, office or business network.</p>',
                'type' => self::TYPE_PORTS,
                'parent' => 'ports',
                'rule' => ['ports' => $ports],
            ];
        }

        // Brands
        $definitions['brands'] = [
            'name' => 'Network Switch Brands',
            'meta' => 'Shop network switches by brand in Kenya - TP-Link, Ubiquiti, MikroTik, D-Link, Tenda, Cisco, Huawei and more.',
            'description' => '<p>Browse network switches from leading brands available in Kenya.</p>',
            'type' => self::TYPE_BRANDS,
            'parent' => null,
            'rule' => [],
        ];
        foreach (self::brands() as $slug => $brand) {
            $definitions[$slug.'-switches'] = [
                'name' => $brand.' Switches',
                'meta' => 'Shop '.$brand.' switches in Kenya with current prices, stock and delivery options.',
                'description' => '<p>Browse '.$brand.' network switches available in Kenya, including PoE, managed, unmanaged and gigabit models.</p>',
                'type' => self::TYPE_BRANDS,
                'parent' => 'brands',
                'rule' => ['brand' => $slug],
            ];
        }

        // Brand subcategories (section 8)
        foreach (self::brandSubcategories() as $slug => $sub) {
            $definitions[$slug] = [
                'name' => $sub['name'],
                'meta' => 'Shop '.$sub['name'].' in Kenya with current prices and stock availability.',
                'description' => '<p>Browse '.$sub['name'].' available in Kenya.</p>',
                'type' => self::TYPE_BRANDS,
                'parent' => $sub['parent'],
                'rule' => $sub['rule'],
            ];
        }

        // Solutions
        $definitions['solutions'] = [
            'name' => 'Network Switch Solutions',
            'meta' => 'Shop network switches by application in Kenya - CCTV, WiFi, office, ISP, enterprise and more.',
            'description' => '<p>Find the right switch for what you are building, whether CCTV, WiFi, office, ISP or enterprise networking.</p>',
            'type' => self::TYPE_SOLUTIONS,
            'parent' => null,
            'rule' => [],
        ];
        $solutionMap = [
            'cctv-switches' => ['CCTV PoE Switches', 'cctv', 'PoE switches for powering IP cameras, Hikvision and Dahua systems, and long-range surveillance installations.'],
            'wifi-access-point-switches' => ['WiFi Access Point Switches', 'wifi', 'PoE switches for powering WiFi access points from Ubiquiti, TP-Link Omada and other brands.'],
            'office-switches' => ['Office Network Switches', 'office', 'Switches for building reliable, high-performance office networks.'],
            'isp-switches' => ['ISP Network Switches', 'isp', 'High-performance switches for internet service providers and broadband networks.'],
            'hotel-switches' => ['Hotel Network Switches', 'hotel', 'Switches for guest WiFi, IPTV and hotel management networks.'],
            'school-switches' => ['School Network Switches', 'school', 'Switches for campus and classroom networks, CCTV and WiFi.'],
            'enterprise-switches' => ['Enterprise Network Switches', 'enterprise', 'Managed and stackable switches for enterprise and corporate networks.'],
            'data-centre-switches' => ['Data Centre Switches', 'data-centre', 'High-speed 10G/25G/40G/100G switches for data centre and core networks.'],
            'cyber-cafe-switches' => ['Cyber Cafe Network Switches', 'cyber-cafe', 'Affordable switches for cyber cafes and gaming networks.'],
            'small-business-switches' => ['Small Business Network Switches', 'small-business', 'Easy-to-manage switches for small businesses and startups.'],
            'home-switches' => ['Home Network Switches', 'home', 'Simple switches for expanding home networks.'],
            'voip-phone-switches' => ['VoIP Phone Switches', 'voip', 'PoE switches for powering VoIP desk phones.'],
            'access-control-switches' => ['Access Control Network Switches', 'access-control', 'Switches for powering access control readers and security systems.'],
        ];
        foreach ($solutionMap as $slug => [$name, $app, $summary]) {
            $definitions[$slug] = [
                'name' => $name,
                'meta' => $name.' in Kenya - '.$summary,
                'description' => '<p>'.$summary.'</p>',
                'type' => self::TYPE_SOLUTIONS,
                'parent' => 'solutions',
                'rule' => ['applications' => [$app]],
            ];
        }

        return $definitions;
    }

    /**
     * @return array<string, array{name: string, meta: string, description: string, type: string, parent: ?string, rule: array<string, mixed>}>
     */
    public static function definition(string $slug): ?array
    {
        return self::definitions()[Str::slug($slug)] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ruleFor(string $slug): array
    {
        return self::definition($slug)['rule'] ?? [];
    }

    /**
     * Apply a category's attribute rule (and any descendant rules for grouping
     * categories) to a product query.
     */
    public static function applyCategory(Builder $query, Category $category): Builder
    {
        $slug = Str::slug($category->slug);

        // Root grouping pages: match the union of their direct children.
        if ($category->parent_id === null && in_array($category->type, [self::TYPE_PORTS, self::TYPE_BRANDS, self::TYPE_SOLUTIONS], true)) {
            $childRules = collect($category->children->pluck('slug'))
                ->map(fn (string $slug): array => self::ruleFor($slug))
                ->filter()
                ->values();

            if ($childRules->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $group) use ($childRules): void {
                foreach ($childRules as $rule) {
                    $group->orWhere(function (Builder $or) use ($rule): void {
                        self::applyRule($or, $rule);
                    });
                }
            });
        }

        // "network-switches" root shows everything in the switch catalogue.
        if ($slug === 'network-switches') {
            return $query;
        }

        $rule = self::ruleFor($slug);

        // A grouping category with no rule of its own (e.g. "poe-switches" is a
        // rule category itself, so this only applies to generic parents).
        if ($rule === [] && $category->children->isNotEmpty()) {
            $childRules = collect($category->children->pluck('slug'))
                ->map(fn (string $slug): array => self::ruleFor($slug))
                ->filter()
                ->values();

            if ($childRules->isEmpty()) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $group) use ($childRules): void {
                foreach ($childRules as $childRule) {
                    $group->orWhere(function (Builder $or) use ($childRule): void {
                        self::applyRule($or, $childRule);
                    });
                }
            });
        }

        if ($rule === []) {
            // Fallback: direct category assignment.
            $ids = array_merge([$category->id], $category->descendantIds());

            return $query->whereIn('category_id', $ids);
        }

        return self::applyRule($query, $rule);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    public static function applyRule(Builder $query, array $rule): Builder
    {
        foreach ($rule as $key => $value) {
            switch ($key) {
                case 'brand':
                    $aliases = self::brandAliases((string) $value);
                    $query->whereRaw(
                        'LOWER(COALESCE(brand, \'\')) IN ('.implode(',', array_fill(0, count($aliases), '?')).')',
                        $aliases
                    );
                    break;

                case 'ports':
                    $portValue = (int) $value;
                    $query->where(function (Builder $w) use ($portValue): void {
                        $w->where('rj45_ports', $portValue)
                            ->orWhere(fn (Builder $x) => $x->whereNull('rj45_ports')->where('port_count', $portValue));
                    });
                    break;

                case 'poe':
                    if ($value === 'yes') {
                        $query->where(function (Builder $w): void {
                            $w->whereIn('poe_standard', ['af', 'at', 'bt'])
                                ->orWhere('poe_ports', '>', 0);
                        });
                    } else {
                        $query->where(function (Builder $w): void {
                            $w->where(function (Builder $x): void {
                                $x->whereNull('poe_standard')->orWhereNotIn('poe_standard', ['af', 'at', 'bt']);
                            })->where(function (Builder $x): void {
                                $x->whereNull('poe_ports')->orWhere('poe_ports', '<=', 0);
                            });
                        });
                    }
                    break;

                case 'poe_standard':
                    $query->whereIn('poe_standard', (array) $value);
                    break;

                case 'management':
                    $query->whereIn('management_type', (array) $value);
                    break;

                case 'layer':
                    $query->whereIn('layer', (array) $value);
                    break;

                case 'speed':
                    $query->whereIn('port_speed', (array) $value);
                    break;

                case 'uplink':
                    $query->whereIn('uplink_type', (array) $value);
                    break;

                case 'sfp_ports':
                    $query->where('sfp_ports', '>', 0);
                    break;

                case 'sfp_plus_ports':
                    $query->where('sfp_plus_ports', '>', 0);
                    break;

                case 'sfp28_ports':
                    $query->where('sfp28_ports', '>', 0);
                    break;

                case 'qsfp_ports':
                    $query->where('qsfp_ports', '>', 0);
                    break;

                case 'fiber':
                    $query->where(function (Builder $w): void {
                        $w->whereIn('uplink_type', ['sfp', 'sfp+', 'sfp28', 'qsfp+', 'qsfp28'])
                            ->orWhere('sfp_ports', '>', 0)
                            ->orWhere('sfp_plus_ports', '>', 0)
                            ->orWhere('sfp28_ports', '>', 0)
                            ->orWhere('qsfp_ports', '>', 0);
                    });
                    break;

                case 'industrial':
                    $query->where('industrial', true);
                    break;

                case 'stackable':
                    $query->where('stackable', true);
                    break;

                case 'outdoor':
                    $query->where('outdoor', true);
                    break;

                case 'din_rail':
                    $query->where('din_rail', true);
                    break;

                case 'rackmount':
                    $query->where('rackmount', true);
                    break;

                case 'applications':
                    foreach ((array) $value as $app) {
                        $query->whereJsonContains('applications', $app);
                    }
                    break;

                case 'keyword':
                    $needle = (string) $value;
                    $query->where(function (Builder $w) use ($needle): void {
                        $w->where('name', 'like', '%'.$needle.'%')
                            ->orWhere('model_number', 'like', '%'.$needle.'%')
                            ->orWhere('sku', 'like', '%'.$needle.'%');
                    });
                    break;
            }
        }

        return $query;
    }

    /**
     * The friendly URL for a category based on its type.
     */
    public static function urlFor(Category $category): string
    {
        $type = $category->type ?: self::TYPE_SHOP;

        return match ($type) {
            self::TYPE_PORTS => route('ports.show', ['category' => $category->slug]),
            self::TYPE_BRANDS => route('brands.show', ['category' => $category->slug]),
            self::TYPE_SOLUTIONS => route('solutions.show', ['category' => $category->slug]),
            default => route('shop.show', ['category' => $category->slug]),
        };
    }

    /**
     * Legacy MikroTik category slugs => new switch-catalogue slugs.
     *
     * @return array<string, string>
     */
    public static function legacyCategoryRedirects(): array
    {
        return [
            'mikrotik-switches' => 'mikrotik-switches',
            'mikrotik-switch-prices-in-kenya' => 'mikrotik-switches',
            'mikrotik-switches-in-kenya' => 'mikrotik-switches',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function legacyProductSlugMap(): array
    {
        return [];
    }

    /**
     * Navigation structure for the mega menu and mobile menu.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function navigation(): array
    {
        return [
            [
                'label' => 'Shop Switches',
                'url' => route('shop.show', ['category' => 'network-switches']),
                'children' => [
                    ['label' => 'By Type', 'items' => [
                        ['label' => 'Unmanaged Switches', 'slug' => 'unmanaged-switches'],
                        ['label' => 'Smart Managed Switches', 'slug' => 'smart-managed-switches'],
                        ['label' => 'Managed Switches', 'slug' => 'managed-switches'],
                        ['label' => 'Cloud Managed Switches', 'slug' => 'cloud-managed-switches'],
                        ['label' => 'Layer 2 Switches', 'slug' => 'layer-2-switches'],
                        ['label' => 'Layer 3 Switches', 'slug' => 'layer-3-switches'],
                        ['label' => 'Industrial Switches', 'slug' => 'industrial-switches'],
                        ['label' => 'Outdoor Switches', 'slug' => 'outdoor-switches'],
                        ['label' => 'Aggregation Switches', 'slug' => 'aggregation-switches'],
                        ['label' => 'Stackable Switches', 'slug' => 'stackable-switches'],
                    ]],
                    ['label' => 'By PoE', 'items' => [
                        ['label' => 'PoE Switches', 'slug' => 'poe-switches'],
                        ['label' => 'PoE+ Switches', 'slug' => 'poe-plus-switches'],
                        ['label' => 'PoE++ Switches', 'slug' => 'poe-plus-plus-switches'],
                        ['label' => 'Non-PoE Switches', 'slug' => 'non-poe-switches'],
                    ]],
                    ['label' => 'By Speed', 'items' => [
                        ['label' => 'Fast Ethernet Switches', 'slug' => 'fast-ethernet-switches'],
                        ['label' => 'Gigabit Switches', 'slug' => 'gigabit-switches'],
                        ['label' => '2.5G Switches', 'slug' => '2-5g-switches'],
                        ['label' => '5G Switches', 'slug' => '5g-switches'],
                        ['label' => '10G Switches', 'slug' => '10g-switches'],
                        ['label' => '25G Switches', 'slug' => '25g-switches'],
                        ['label' => '40G Switches', 'slug' => '40g-switches'],
                        ['label' => '100G Switches', 'slug' => '100g-switches'],
                    ]],
                    ['label' => 'Fiber Switches', 'items' => [
                        ['label' => 'Fiber Switches', 'slug' => 'fiber-switches'],
                        ['label' => 'SFP Switches', 'slug' => 'sfp-switches'],
                        ['label' => 'SFP+ Switches', 'slug' => 'sfp-plus-switches'],
                        ['label' => 'SFP28 Switches', 'slug' => 'sfp28-switches'],
                        ['label' => 'QSFP+ Switches', 'slug' => 'qsfp-switches'],
                        ['label' => 'QSFP28 Switches', 'slug' => 'qsfp28-switches'],
                        ['label' => 'Fiber Aggregation Switches', 'slug' => 'fiber-aggregation-switches'],
                    ]],
                ],
            ],
            [
                'label' => 'PoE Switches',
                'url' => route('shop.show', ['category' => 'poe-switches']),
                'children' => [
                    ['label' => 'PoE by Standard', 'items' => [
                        ['label' => 'PoE (802.3af) Switches', 'slug' => 'poe-switches'],
                        ['label' => 'PoE+ (802.3at) Switches', 'slug' => 'poe-plus-switches'],
                        ['label' => 'PoE++ (802.3bt) Switches', 'slug' => 'poe-plus-plus-switches'],
                    ]],
                    ['label' => 'PoE by Ports', 'items' => [
                        ['label' => '4 Port PoE Switches', 'slug' => '4-port-poe-switches'],
                        ['label' => '8 Port PoE Switches', 'slug' => '8-port-poe-switches'],
                        ['label' => '16 Port PoE Switches', 'slug' => '16-port-poe-switches'],
                        ['label' => '24 Port PoE Switches', 'slug' => '24-port-poe-switches'],
                        ['label' => '48 Port PoE Switches', 'slug' => '48-port-poe-switches'],
                    ]],
                ],
            ],
            [
                'label' => 'Managed Switches',
                'url' => route('shop.show', ['category' => 'managed-switches']),
                'children' => [
                    ['label' => 'Management Level', 'items' => [
                        ['label' => 'Unmanaged Switches', 'slug' => 'unmanaged-switches'],
                        ['label' => 'Smart Managed Switches', 'slug' => 'smart-managed-switches'],
                        ['label' => 'Managed Switches', 'slug' => 'managed-switches'],
                        ['label' => 'Cloud Managed Switches', 'slug' => 'cloud-managed-switches'],
                        ['label' => 'Layer 2 Switches', 'slug' => 'layer-2-switches'],
                        ['label' => 'Layer 3 Switches', 'slug' => 'layer-3-switches'],
                    ]],
                ],
            ],
            [
                'label' => 'By Ports',
                'url' => route('ports.index'),
                'children' => [
                    ['label' => 'Popular Port Counts', 'items' => [
                        ['label' => '5 Port Switches', 'slug' => '5-port-switches'],
                        ['label' => '8 Port Switches', 'slug' => '8-port-switches'],
                        ['label' => '16 Port Switches', 'slug' => '16-port-switches'],
                        ['label' => '24 Port Switches', 'slug' => '24-port-switches'],
                        ['label' => '48 Port Switches', 'slug' => '48-port-switches'],
                    ]],
                    ['label' => 'Other Port Counts', 'items' => [
                        ['label' => '4 Port Switches', 'slug' => '4-port-switches'],
                        ['label' => '10 Port Switches', 'slug' => '10-port-switches'],
                        ['label' => '28 Port Switches', 'slug' => '28-port-switches'],
                        ['label' => '52 Port Switches', 'slug' => '52-port-switches'],
                    ]],
                ],
            ],
            [
                'label' => 'By Brand',
                'url' => route('brands.index'),
                'children' => [
                    ['label' => 'Top Brands', 'items' => [
                        ['label' => 'TP-Link Switches', 'slug' => 'tp-link-switches'],
                        ['label' => 'Ubiquiti Switches', 'slug' => 'ubiquiti-switches'],
                        ['label' => 'MikroTik Switches', 'slug' => 'mikrotik-switches'],
                        ['label' => 'D-Link Switches', 'slug' => 'd-link-switches'],
                        ['label' => 'Tenda Switches', 'slug' => 'tenda-switches'],
                        ['label' => 'Cisco Switches', 'slug' => 'cisco-switches'],
                    ]],
                    ['label' => 'More Brands', 'items' => [
                        ['label' => 'Netis Switches', 'slug' => 'netis-switches'],
                        ['label' => 'Ruijie Switches', 'slug' => 'ruijie-switches'],
                        ['label' => 'Huawei Switches', 'slug' => 'huawei-switches'],
                        ['label' => 'Hikvision Switches', 'slug' => 'hikvision-switches'],
                        ['label' => 'Dahua Switches', 'slug' => 'dahua-switches'],
                        ['label' => 'Aruba Switches', 'slug' => 'aruba-switches'],
                        ['label' => 'Zyxel Switches', 'slug' => 'zyxel-switches'],
                        ['label' => 'Mercusys Switches', 'slug' => 'mercusys-switches'],
                        ['label' => 'Grandstream Switches', 'slug' => 'grandstream-switches'],
                    ]],
                ],
            ],
            [
                'label' => 'Solutions',
                'url' => route('solutions.index'),
                'children' => [
                    ['label' => 'By Application', 'items' => [
                        ['label' => 'CCTV PoE Switches', 'slug' => 'cctv-switches'],
                        ['label' => 'WiFi Access Point Switches', 'slug' => 'wifi-access-point-switches'],
                        ['label' => 'Office Network Switches', 'slug' => 'office-switches'],
                        ['label' => 'ISP Network Switches', 'slug' => 'isp-switches'],
                        ['label' => 'Enterprise Network Switches', 'slug' => 'enterprise-switches'],
                        ['label' => 'Hotel Network Switches', 'slug' => 'hotel-switches'],
                        ['label' => 'School Network Switches', 'slug' => 'school-switches'],
                        ['label' => 'Data Centre Switches', 'slug' => 'data-centre-switches'],
                        ['label' => 'Home Network Switches', 'slug' => 'home-switches'],
                        ['label' => 'Small Business Switches', 'slug' => 'small-business-switches'],
                    ]],
                ],
            ],
        ];
    }

    /**
     * Resolve a navigation slug to its canonical category URL.
     */
    public static function categoryUrl(string $slug): string
    {
        $definition = self::definition($slug);
        if (! $definition) {
            return route('home');
        }

        return match ($definition['type']) {
            self::TYPE_PORTS => route('ports.show', ['category' => $slug]),
            self::TYPE_BRANDS => route('brands.show', ['category' => $slug]),
            self::TYPE_SOLUTIONS => route('solutions.show', ['category' => $slug]),
            default => route('shop.show', ['category' => $slug]),
        };
    }

    /**
     * @return array<int, array{question: string, options: array<int, array{value: string, label: string}>}>
     */
    public static function finderSteps(): array
    {
        return [
            [
                'question' => 'How many devices do you want to connect?',
                'options' => [
                    ['value' => '4', 'label' => 'Up to 4'],
                    ['value' => '8', 'label' => 'Up to 8'],
                    ['value' => '16', 'label' => 'Up to 16'],
                    ['value' => '24', 'label' => 'Up to 24'],
                    ['value' => '48', 'label' => 'Up to 48'],
                    ['value' => '48+', 'label' => 'More than 48'],
                ],
            ],
            [
                'question' => 'Do the connected devices require PoE (power over Ethernet)?',
                'options' => [
                    ['value' => 'yes', 'label' => 'Yes'],
                    ['value' => 'no', 'label' => 'No'],
                    ['value' => 'not-sure', 'label' => 'Not sure'],
                ],
            ],
            [
                'question' => 'What are you connecting?',
                'options' => [
                    ['value' => 'cctv', 'label' => 'CCTV Cameras'],
                    ['value' => 'wifi', 'label' => 'WiFi Access Points'],
                    ['value' => 'computers', 'label' => 'Computers'],
                    ['value' => 'voip', 'label' => 'VoIP Phones'],
                    ['value' => 'servers', 'label' => 'Servers'],
                    ['value' => 'access-control', 'label' => 'Access Control'],
                    ['value' => 'mixed', 'label' => 'Mixed Network Equipment'],
                ],
            ],
            [
                'question' => 'What network speed do you require?',
                'options' => [
                    ['value' => '100m', 'label' => '100 Mbps'],
                    ['value' => '1g', 'label' => '1 Gigabit'],
                    ['value' => '2.5g', 'label' => '2.5 Gigabit'],
                    ['value' => '10g', 'label' => '10 Gigabit'],
                    ['value' => 'not-sure', 'label' => 'Not sure'],
                ],
            ],
            [
                'question' => 'Do you require network management?',
                'options' => [
                    ['value' => 'none', 'label' => 'No, plug and play'],
                    ['value' => 'basic', 'label' => 'Basic Management'],
                    ['value' => 'vlan', 'label' => 'VLAN Support'],
                    ['value' => 'full', 'label' => 'Full Managed Switch'],
                    ['value' => 'l3', 'label' => 'Layer 3'],
                    ['value' => 'not-sure', 'label' => 'Not sure'],
                ],
            ],
            [
                'question' => 'What is your budget? (optional)',
                'options' => [
                    ['value' => '5000', 'label' => 'Under KSh 5,000'],
                    ['value' => '15000', 'label' => 'Under KSh 15,000'],
                    ['value' => '30000', 'label' => 'Under KSh 30,000'],
                    ['value' => '60000', 'label' => 'Under KSh 60,000'],
                    ['value' => '', 'label' => 'No budget limit'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, string>  $answers
     */
    public static function finderQuery(array $answers): Builder
    {
        $query = Product::query()
            ->with(['vendor', 'category', 'images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('sort_order')])
            ->active();

        $devices = $answers['devices'] ?? null;
        if ($devices && $devices !== '48+') {
            $query->where('port_count', '>=', (int) $devices);
        } elseif ($devices === '48+') {
            $query->where('port_count', '>', 48);
        }

        $poe = $answers['poe'] ?? null;
        if ($poe === 'yes') {
            $query->where(function (Builder $w): void {
                $w->whereIn('poe_standard', ['af', 'at', 'bt'])->orWhere('poe_ports', '>', 0);
            });
        } elseif ($poe === 'no') {
            $query->where(function (Builder $w): void {
                $w->where(fn ($x) => $x->whereNull('poe_standard')->orWhereNotIn('poe_standard', ['af', 'at', 'bt']))
                    ->where(fn ($x) => $x->whereNull('poe_ports')->orWhere('poe_ports', '<=', 0));
            });
        }

        $deviceType = $answers['devices_type'] ?? null;
        $application = null;
        if ($deviceType && $deviceType !== 'mixed') {
            $application = match ($deviceType) {
                'cctv', 'wifi', 'voip', 'access-control' => $deviceType,
                'computers' => 'office',
                'servers' => 'enterprise',
                default => null,
            };
        }
        if ($deviceType === 'cctv' || $deviceType === 'wifi' || $deviceType === 'voip') {
            $query->where(function (Builder $w): void {
                $w->whereIn('poe_standard', ['af', 'at', 'bt'])->orWhere('poe_ports', '>', 0);
            });
        }
        if ($application) {
            $query->whereJsonContains('applications', $application);
        }

        $speed = $answers['speed'] ?? null;
        if ($speed && $speed !== 'not-sure') {
            $query->where('port_speed', $speed);
        }

        $management = $answers['management'] ?? null;
        if ($management && $management !== 'not-sure') {
            $query->where(function (Builder $w) use ($management): void {
                $types = match ($management) {
                    'none' => ['unmanaged'],
                    'basic' => ['smart', 'unmanaged'],
                    'vlan' => ['smart', 'managed', 'cloud'],
                    'full' => ['managed', 'cloud'],
                    'l3' => ['managed', 'cloud'],
                    default => [],
                };
                if ($types !== []) {
                    $w->whereIn('management_type', $types);
                    if ($management === 'l3') {
                        $w->where('layer', 'l3');
                    }
                }
            });
        }

        $budget = $answers['budget'] ?? null;
        if ($budget && $budget !== '') {
            $query->where('price', '<=', (float) $budget);
        }

        return $query;
    }

    /**
     * @return array<int, string>
     */
    public static function searchableKeywords(): array
    {
        return ['port', 'poe', 'gigabit', 'sfp', 'managed', 'unmanaged', 'switch', '10g', '2.5g'];
    }

    /**
     * @return array<string, string>
     */
    public static function blogCategories(): array
    {
        return [
            'buying-guides' => 'Buying Guides',
            'comparisons' => 'Network Switch Comparisons',
            'poe-guides' => 'PoE Guides',
            'cctv-networking' => 'CCTV Networking',
            'enterprise-switching' => 'Enterprise Switching',
            'fiber-sfp' => 'Fiber & SFP',
            'configuration' => 'Network Switch Configuration',
            'troubleshooting' => 'Troubleshooting',
            'product-reviews' => 'Product Reviews',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            'featured' => 'Featured',
            'newest' => 'Newest',
            'price_asc' => 'Price: Low to High',
            'price_desc' => 'Price: High to Low',
            'name_asc' => 'Name (A-Z)',
            'ports_asc' => 'Port Count',
            'poe_budget_desc' => 'PoE Budget (High to Low)',
        ];
    }

    public static function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'name_asc' => $query->orderBy('name'),
            'ports_asc' => $query->orderByRaw('port_count IS NULL, port_count ASC'),
            'poe_budget_desc' => $query->orderByRaw('poe_budget IS NULL, poe_budget DESC'),
            'newest' => $query->latest(),
            default => $query->latest(),
        };
    }

    /**
     * Apply faceted filters to a product query.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['brand'])) {
            $aliases = self::brandAliases((string) $filters['brand']);
            $query->whereRaw(
                'LOWER(COALESCE(brand, \'\')) IN ('.implode(',', array_fill(0, count($aliases), '?')).')',
                $aliases
            );
        }

        if (! empty($filters['ports'])) {
            $portValue = (int) $filters['ports'];
            $query->where(function (Builder $w) use ($portValue): void {
                $w->where('rj45_ports', $portValue)
                    ->orWhere(fn (Builder $x) => $x->whereNull('rj45_ports')->where('port_count', $portValue));
            });
        }

        if (! empty($filters['management'])) {
            $query->where('management_type', (string) $filters['management']);
        }

        if (! empty($filters['poe'])) {
            if ($filters['poe'] === 'non-poe') {
                $query->where(function (Builder $w): void {
                    $w->where(fn ($x) => $x->whereNull('poe_standard')->orWhereNotIn('poe_standard', ['af', 'at', 'bt']))
                        ->where(fn ($x) => $x->whereNull('poe_ports')->orWhere('poe_ports', '<=', 0));
                });
            } else {
                $query->where('poe_standard', (string) $filters['poe']);
            }
        }

        if (! empty($filters['speed'])) {
            $query->where('port_speed', (string) $filters['speed']);
        }

        if (! empty($filters['uplink'])) {
            $query->where('uplink_type', (string) $filters['uplink']);
        }

        if (! empty($filters['budget'])) {
            $range = self::poeBudgetRanges()[(string) $filters['budget']] ?? null;
            if ($range) {
                if ($range['min'] !== null) {
                    $query->where('poe_budget', '>=', $range['min']);
                }
                if ($range['max'] !== null) {
                    $query->where('poe_budget', '<=', $range['max']);
                }
            }
        }

        if (! empty($filters['install'])) {
            $query->where((string) $filters['install'], true);
        }

        if (! empty($filters['min'])) {
            $query->where('price', '>=', (float) $filters['min']);
        }

        if (! empty($filters['max'])) {
            $query->where('price', '<=', (float) $filters['max']);
        }

        if (! empty($filters['avail'])) {
            match ((string) $filters['avail']) {
                'in_stock' => $query->where('stock', '>', 0)->where(fn ($w) => $w->whereNull('availability')->orWhere('availability', '!=', 'out_of_stock')),
                'out_of_stock' => $query->where(fn ($w) => $w->where('stock', '<=', 0)->orWhere('availability', 'out_of_stock')),
                'preorder' => $query->where('availability', 'preorder'),
                default => null,
            };
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public static function extractFilters(\Illuminate\Http\Request $request): array
    {
        $filters = [];
        foreach (['brand', 'ports', 'management', 'poe', 'speed', 'uplink', 'budget', 'install', 'avail', 'min', 'max'] as $key) {
            $value = $request->query($key);
            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }
}
