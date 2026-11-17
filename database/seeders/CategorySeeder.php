<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryField;
use Database\Seeders\Concerns\TranslatesNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starting categories + custom fields (decision #8: products, services, jobs).
 * Admin can add/edit more later without code.
 * Safe to run again: updates names/fields, never changes on/off switches.
 */
class CategorySeeder extends Seeder
{
    use TranslatesNames;

    public function run(): void
    {
        foreach ($this->tree() as $sort => $main) {
            $parent = $this->upsert(null, $main, $sort);

            foreach ($main['children'] as $childSort => $child) {
                $category = $this->upsert($parent, $child, $childSort);

                foreach ($child['fields'] ?? [] as $fieldSort => $field) {
                    $this->field($category, $field, $fieldSort);
                }
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    private function upsert(?Category $parent, array $data, int $sort): Category
    {
        $slug = $data['slug'] ?? Str::slug($data['name']);
        $category = Category::firstOrNew(['slug' => $slug]);
        $category->fill([
            'parent_id' => $parent?->id,
            'name' => $this->names($category->exists ? $category->getAttribute('name') : null, $data['name']),
            'icon' => $data['icon'] ?? $parent->icon ?? 'tag',
            'has_condition' => $data['condition'] ?? $parent->has_condition ?? true,
            'sort' => $sort,
        ]);
        if (! $category->exists) {
            $category->enabled = true;
            $category->popular = $data['popular'] ?? false;
        }
        $category->save();

        return $category;
    }

    /** @param  array<string, mixed>  $f */
    private function field(Category $category, array $f, int $sort): void
    {
        $field = CategoryField::firstOrNew(['category_id' => $category->id, 'key' => $f['key']]);
        $options = null;
        if (isset($f['options'])) {
            $old = collect($field->options ?? [])->keyBy('value');
            $options = array_map(fn (string $o) => [
                'value' => Str::slug($o),
                'label' => $this->names($old->get(Str::slug($o))['label'] ?? null, $o),
            ], $f['options']);
        }

        $field->fill([
            'type' => $f['type'],
            'label' => $this->names($field->exists ? $field->label : null, $f['label']),
            'options' => $options,
            'unit' => $f['unit'] ?? null,
            'required' => $f['required'] ?? false,
            'filterable' => $f['filterable'] ?? true,
            'sort' => $sort,
        ])->save();
    }

    /** @return list<array<string, mixed>> */
    private function tree(): array
    {
        $year = ['key' => 'year', 'type' => 'number', 'label' => 'Year'];
        $carBrands = ['Toyota', 'Suzuki', 'Nissan', 'Mitsubishi', 'Honda', 'Hyundai', 'Isuzu', 'Mazda', 'Mercedes-Benz', 'Land Rover', 'Ford', 'Other'];
        $fuel = ['key' => 'fuel', 'type' => 'select', 'label' => 'Fuel', 'options' => ['Petrol', 'Diesel', 'Hybrid', 'Electric']];
        $gear = ['key' => 'transmission', 'type' => 'select', 'label' => 'Gear', 'options' => ['Automatic', 'Manual']];
        $km = ['key' => 'mileage', 'type' => 'number', 'label' => 'Kilometres', 'unit' => 'km'];
        $phoneBrands = ['Apple', 'Samsung', 'Tecno', 'Infinix', 'Xiaomi', 'Huawei', 'Oppo', 'Nokia', 'Google', 'Other'];
        $storage = ['key' => 'storage', 'type' => 'select', 'label' => 'Storage', 'options' => ['16 GB', '32 GB', '64 GB', '128 GB', '256 GB', '512 GB', '1 TB']];
        $home = [
            ['key' => 'bedrooms', 'type' => 'number', 'label' => 'Bedrooms', 'required' => true],
            ['key' => 'bathrooms', 'type' => 'number', 'label' => 'Bathrooms'],
            ['key' => 'size', 'type' => 'number', 'label' => 'Size', 'unit' => 'm²'],
            ['key' => 'furnished', 'type' => 'boolean', 'label' => 'Furnished'],
        ];
        $plotSize = [['key' => 'size', 'type' => 'number', 'label' => 'Size', 'unit' => 'm²', 'required' => true]];
        $quantity = [['key' => 'quantity', 'type' => 'number', 'label' => 'How many']];
        $partTypes = ['Engine & Gearbox', 'Wheels & Tyres', 'Car Care', 'Oils & Fluids', 'Lights & Electrics', 'Body Parts', 'Batteries', 'Car Audio & Screens', 'Other Parts'];
        $jobType = [['key' => 'job_type', 'type' => 'select', 'label' => 'Job type', 'options' => ['Full-time', 'Part-time', 'Contract', 'Temporary']]];

        return [
            ['name' => 'Vehicles', 'icon' => 'car', 'children' => [
                ['name' => 'Cars', 'icon' => 'car', 'popular' => true, 'fields' => [
                    ['key' => 'brand', 'type' => 'select', 'label' => 'Brand', 'options' => $carBrands, 'required' => true],
                    ['key' => 'model', 'type' => 'text', 'label' => 'Model', 'filterable' => false],
                    $year, $km, $fuel, $gear,
                ]],
                ['name' => 'Motorcycles & Bajaj', 'slug' => 'motorcycles', 'icon' => 'motorbike', 'fields' => [$year]],
                // Slug kept from "Trucks & Buses" so old links keep working; buses have their own sub category now.
                ['name' => 'Trucks', 'slug' => 'trucks-buses', 'icon' => 'truck', 'fields' => [$year, $km, $fuel]],
                ['name' => 'Buses & Minibuses', 'slug' => 'buses', 'icon' => 'bus', 'fields' => [$year, $km, $fuel,
                    ['key' => 'seats', 'type' => 'number', 'label' => 'Seats']]],
                // A third level (Engine, Tyres…) is a "Part type" list: one filter instead of 8 tiny sub categories.
                ['name' => 'Vehicle Parts & Accessories', 'slug' => 'vehicle-parts', 'icon' => 'cog', 'fields' => [
                    ['key' => 'part_type', 'type' => 'select', 'label' => 'Part type', 'required' => true, 'options' => $partTypes],
                ]],
                ['name' => 'Boats', 'icon' => 'sailboat'],
            ]],
            ['name' => 'Property', 'icon' => 'house', 'condition' => false, 'children' => [
                ['name' => 'Houses & Apartments for Rent', 'slug' => 'houses-for-rent', 'icon' => 'key', 'popular' => true, 'fields' => $home],
                ['name' => 'Houses & Apartments for Sale', 'slug' => 'houses-for-sale', 'icon' => 'house', 'popular' => true, 'fields' => $home],
                ['name' => 'Land & Plots', 'slug' => 'land', 'icon' => 'land-plot', 'popular' => true, 'fields' => $plotSize],
                ['name' => 'Shops & Offices', 'slug' => 'commercial-property', 'icon' => 'building', 'fields' => $plotSize],
                ['name' => 'Rooms for Rent', 'slug' => 'rooms', 'icon' => 'bed-single', 'fields' => [
                    ['key' => 'furnished', 'type' => 'boolean', 'label' => 'Furnished'],
                ]],
            ]],
            ['name' => 'Phones & Tablets', 'slug' => 'phones-tablets', 'icon' => 'smartphone', 'children' => [
                ['name' => 'Mobile Phones', 'icon' => 'smartphone', 'popular' => true, 'fields' => [
                    ['key' => 'brand', 'type' => 'select', 'label' => 'Brand', 'options' => $phoneBrands, 'required' => true],
                    $storage,
                ]],
                ['name' => 'Tablets', 'icon' => 'tablet', 'fields' => [['key' => 'brand', 'type' => 'select', 'label' => 'Brand', 'options' => $phoneBrands], $storage]],
                ['name' => 'Phone Accessories', 'icon' => 'headphones'],
            ]],
            ['name' => 'Electronics', 'icon' => 'laptop', 'children' => [
                ['name' => 'Laptops & Computers', 'icon' => 'laptop', 'popular' => true, 'fields' => [
                    ['key' => 'brand', 'type' => 'select', 'label' => 'Brand', 'options' => ['HP', 'Dell', 'Lenovo', 'Apple', 'Asus', 'Acer', 'Other']],
                    ['key' => 'ram', 'type' => 'select', 'label' => 'RAM', 'options' => ['4 GB', '8 GB', '16 GB', '32 GB+']],
                    $storage,
                ]],
                ['name' => 'Computer Accessories', 'icon' => 'mouse'],
                ['name' => 'TV & Audio', 'icon' => 'tv'],
                ['name' => 'Solar & Generators', 'slug' => 'solar-generators', 'icon' => 'sun'],
                ['name' => 'Cameras', 'icon' => 'camera'],
                ['name' => 'Other Electronics', 'icon' => 'headphones'],
            ]],
            ['name' => 'Home & Furniture', 'icon' => 'sofa', 'children' => [
                ['name' => 'Furniture', 'icon' => 'sofa', 'popular' => true],
                ['name' => 'Home Appliances', 'icon' => 'refrigerator'],
                ['name' => 'Kitchen', 'icon' => 'utensils'],
                ['name' => 'Home Decor & Carpets', 'slug' => 'home-decor', 'icon' => 'lamp'],
            ]],
            ['name' => 'Fashion', 'icon' => 'shirt', 'children' => [
                ['name' => "Men's Clothing", 'slug' => 'mens-clothing', 'icon' => 'shirt'],
                ['name' => "Women's Clothing", 'slug' => 'womens-clothing', 'icon' => 'shopping-bag'],
                ['name' => 'Traditional Wear', 'slug' => 'traditional-wear', 'icon' => 'gem'],
                ['name' => 'Shoes', 'icon' => 'footprints'],
                ['name' => 'Bags', 'icon' => 'shopping-bag'],
                ['name' => 'Watches & Jewellery', 'slug' => 'watches-jewellery', 'icon' => 'watch'],
            ]],
            ['name' => 'Livestock & Animals', 'slug' => 'livestock', 'icon' => 'paw-print', 'condition' => false, 'children' => [
                ['name' => 'Camels', 'icon' => 'paw-print', 'popular' => true, 'fields' => $quantity],
                ['name' => 'Cows', 'slug' => 'cattle', 'icon' => 'paw-print', 'fields' => $quantity],
                // Slug kept from "Goats & Sheep" so old links keep working; sheep have their own sub category now.
                ['name' => 'Goats', 'slug' => 'goats-sheep', 'icon' => 'paw-print', 'popular' => true, 'fields' => $quantity],
                ['name' => 'Sheep', 'icon' => 'paw-print', 'fields' => $quantity],
                ['name' => 'Poultry', 'icon' => 'bird', 'fields' => $quantity],
                ['name' => 'Animal Feed & Supplies', 'slug' => 'animal-feed', 'icon' => 'wheat'],
                ['name' => 'Pets', 'icon' => 'paw-print'],
            ]],
            ['name' => 'Agriculture & Food', 'slug' => 'agriculture', 'icon' => 'sprout', 'children' => [
                ['name' => 'Farm Machinery', 'icon' => 'tractor'],
                ['name' => 'Seeds & Plants', 'icon' => 'sprout'],
                ['name' => 'Food & Drinks', 'icon' => 'utensils'],
            ]],
            ['name' => 'Services', 'icon' => 'wrench', 'condition' => false, 'children' => [
                ['name' => 'Building & Repair', 'icon' => 'hammer'],
                ['name' => 'Car Repair', 'icon' => 'car'],
                ['name' => 'Phone & Electronics Repair', 'slug' => 'electronics-repair', 'icon' => 'smartphone'],
                ['name' => 'Beauty & Salon', 'slug' => 'salon', 'icon' => 'scissors'],
                ['name' => 'Cleaning', 'icon' => 'hand-helping'],
                ['name' => 'Transport & Moving', 'icon' => 'truck'],
                ['name' => 'Computer & IT', 'icon' => 'laptop'],
                ['name' => 'Events & Weddings', 'slug' => 'events', 'icon' => 'hand-helping'],
                ['name' => 'Classes & Tutoring', 'slug' => 'tutoring', 'icon' => 'hand-helping'],
                ['name' => 'Other Services', 'icon' => 'wrench'],
            ]],
            ['name' => 'Jobs', 'icon' => 'briefcase', 'condition' => false, 'children' => [
                ['name' => 'Job Vacancies', 'slug' => 'job-vacancies', 'icon' => 'briefcase', 'popular' => true, 'fields' => $jobType],
                ['name' => 'Looking for Work', 'slug' => 'looking-for-work', 'icon' => 'user', 'fields' => $jobType],
            ]],
            ['name' => 'Health & Beauty', 'icon' => 'heart-pulse', 'children' => [
                ['name' => 'Beauty & Cosmetics', 'slug' => 'beauty', 'icon' => 'heart-pulse'],
                ['name' => 'Health & Medical', 'slug' => 'health', 'icon' => 'heart-pulse'],
                ['name' => 'Perfume, Oud & Incense', 'slug' => 'perfume', 'icon' => 'spray-can'],
            ]],
            ['name' => 'Babies & Kids', 'icon' => 'baby', 'children' => [
                ['name' => "Kids' Clothing", 'slug' => 'kids-clothing', 'icon' => 'baby'],
                ['name' => 'Toys', 'icon' => 'baby'],
                ['name' => 'Baby Gear', 'icon' => 'baby'],
            ]],
            ['name' => 'Sports & Hobbies', 'icon' => 'dumbbell', 'children' => [
                ['name' => 'Sports Equipment', 'icon' => 'dumbbell'],
                ['name' => 'Bicycles', 'icon' => 'motorbike'],
                ['name' => 'Books & Games', 'icon' => 'tag'],
            ]],
            ['name' => 'Construction & Tools', 'slug' => 'construction', 'icon' => 'hammer', 'children' => [
                ['name' => 'Building Materials', 'icon' => 'hard-hat'],
                ['name' => 'Tools', 'icon' => 'wrench'],
                ['name' => 'Plumbing & Electrical', 'slug' => 'plumbing-electrical', 'icon' => 'plug-zap'],
            ]],
            ['name' => 'Everything Else', 'slug' => 'other', 'icon' => 'package', 'children' => [
                ['name' => 'Other Items', 'icon' => 'package'],
            ]],
        ];
    }
}
