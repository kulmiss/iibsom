<?php

/*
| Demo ad texts by category slug: [title, lowest price, highest price] (USD).
| Prices are rough Mogadishu prices. Field values (brand, year...) are filled
| by the DemoSeeder; a brand in the title is used for the Brand field.
*/

return [
    'cars' => [
        ['Toyota Vitz 2012, automatic', 4500, 6500], ['Toyota Corolla 2010, clean', 5500, 8000], ['Toyota Land Cruiser V8 2015', 38000, 52000],
        ['Toyota Hilux double cabin 2016', 22000, 30000], ['Toyota Noah 2011, 8 seats', 7000, 9500], ['Suzuki Swift 2014', 4800, 6500],
        ['Nissan X-Trail 2013', 9000, 12500], ['Toyota Mark X 2010', 7500, 10000], ['Mitsubishi Pajero 2008', 8000, 11000],
        ['Honda Fit 2013, hybrid', 5000, 7000], ['Toyota Harrier 2012', 12000, 15500], ['Mazda Demio 2011', 3800, 5200],
        ['Hyundai Tucson 2015', 11000, 14000], ['Toyota Probox 2014', 4200, 5800], ['Land Rover Discovery 4', 25000, 33000],
        ['Toyota Surf 2005, diesel', 9500, 13000], ['Toyota Premio 2011', 7000, 9000], ['Nissan Note 2012', 4000, 5500],
    ],
    'motorcycles' => [['Bajaj RE three-wheeler 2020', 2200, 3200], ['Bajaj Boxer 150', 800, 1200], ['TVS King tuk-tuk, good engine', 1800, 2800], ['Yamaha motorcycle 125cc', 900, 1500], ['Honda CG 125', 750, 1100], ['Bajaj Pulsar 180', 1200, 1700]],
    'trucks-buses' => [['Isuzu NPR truck 2009', 14000, 19000], ['Toyota Coaster bus 30 seats', 16000, 24000], ['Mitsubishi Canter tipper', 12000, 17000], ['Isuzu water tanker', 18000, 25000]],
    'vehicle-parts' => [['Toyota tyres 195/65 R15, set of 4', 180, 320], ['Car battery 70Ah, new', 70, 120], ['Land Cruiser headlights', 90, 180], ['Engine oil 5L, original', 25, 45], ['Alloy wheels 17 inch', 250, 450]],

    'houses-for-rent' => [['2 bedroom apartment in Hodan', 250, 450], ['3 bedroom house with garden, Wadajir', 400, 700], ['Furnished flat near KM4', 500, 900], ['1 bedroom studio, Hamar Weyne', 150, 250], ['Big villa for rent, Daynile road', 900, 1600], ['4 bedroom house, Howlwadaag', 600, 1000]],
    'houses-for-sale' => [['3 bedroom house for sale, Hodan', 45000, 85000], ['New villa with parking, Wadajir', 120000, 220000], ['Old house on 20x20 plot, Shibis', 35000, 60000], ['2 storey house, Dharkenley', 90000, 150000], ['Apartment in new building, Waberi', 40000, 70000]],
    'land' => [['Plot 20x20 near Afgooye road', 6000, 15000], ['Land 40x40 with title deed, Garasbaley', 18000, 35000], ['Corner plot, Kaxda district', 9000, 20000], ['Farm land 2 hectares, Lower Shabelle', 15000, 40000], ['Plot 15x30 near the main road', 7000, 14000]],
    'commercial-property' => [['Shop for rent, Bakara market', 300, 700], ['Office space 120 m², KM4', 600, 1200], ['Warehouse 300 m² for rent', 900, 1800], ['Restaurant space, Lido area', 800, 1500]],

    'mobile-phones' => [['Samsung Galaxy A54, 128 GB', 260, 340], ['iPhone 13, 128 GB, clean', 420, 520], ['iPhone 11, 64 GB', 230, 300], ['Tecno Spark 20, new in box', 110, 150], ['Samsung Galaxy S21 Ultra', 380, 480], ['Infinix Hot 40', 120, 160], ['iPhone 14 Pro Max 256 GB', 750, 900], ['Xiaomi Redmi Note 13', 170, 220], ['Nokia 105, new', 15, 25], ['Samsung Galaxy A15', 140, 180]],
    'tablets' => [['iPad 9th generation, 64 GB', 220, 290], ['Samsung Galaxy Tab A8', 160, 210], ['Lenovo tablet for kids', 80, 120]],
    'phone-accessories' => [['Wireless earbuds, new', 15, 35], ['Power bank 20000 mAh', 18, 30], ['Fast charger 25W, original', 10, 20], ['Phone cases, all models', 3, 8]],
    'laptops-computers' => [['HP EliteBook 840 G5, Core i5', 280, 380], ['Dell Latitude 7490, 16 GB RAM', 300, 420], ['MacBook Air M1, 256 GB', 600, 750], ['Lenovo ThinkPad T480', 260, 340], ['Desktop computer + monitor', 200, 320], ['Asus gaming laptop RTX 3050', 650, 850]],
    'tv-audio' => [['Samsung 55 inch smart TV', 380, 480], ['LG 43 inch TV, like new', 220, 290], ['Sound system with speakers', 120, 220], ['Satellite dish and receiver', 40, 80]],
    'cameras' => [['Canon EOS 2000D with lens', 330, 420], ['Security cameras, 4 set', 150, 260], ['Nikon D5300 camera', 300, 380]],
    'other-electronics' => [['Bluetooth speaker JBL', 45, 90], ['PlayStation 4 with 2 controllers', 220, 300], ['Wi-Fi router, 4G', 40, 70]],

    'furniture' => [['Sofa set 7 seater', 450, 800], ['Wooden bed 6x6 with mattress', 250, 420], ['Dining table with 6 chairs', 300, 550], ['Office desk and chair', 120, 220], ['Wardrobe 3 doors', 180, 320], ['Plastic chairs, 20 pieces', 60, 100]],
    'home-appliances' => [['Samsung fridge 300 L', 280, 420], ['Washing machine LG 7 kg', 250, 380], ['Split air conditioner 1.5 HP', 300, 450], ['Water dispenser hot and cold', 70, 120], ['Standing fan, new', 25, 45]],
    'kitchen' => [['Cooking pots set, 10 pieces', 35, 70], ['Gas cooker 4 burners', 120, 220], ['Blender and juicer', 25, 50], ['Big aluminium pots for events', 40, 90]],

    'mens-clothing' => [['Macawis, new, all colours', 8, 18], ['Men suit, 2 pieces', 60, 120], ['Khamis for men', 12, 25], ['Jeans and shirts, bundle', 20, 45]],
    'womens-clothing' => [['Dirac with garbasaar, new', 25, 60], ['Abaya, many sizes', 20, 45], ['Wedding dress, used once', 120, 300], ['Hijab bundle, 10 pieces', 15, 30]],
    'shoes' => [['Nike sports shoes, size 42', 35, 70], ['Leather shoes for men', 25, 50], ['Women sandals, new', 10, 25]],
    'bags' => [['Women handbag, new', 15, 40], ['School backpack', 8, 18], ['Travel suitcase, big', 35, 70]],
    'watches-jewellery' => [['Gold necklace 18k', 400, 1200], ['Casio watch, original', 30, 70], ['Silver ring set', 20, 60], ['Smart watch, new', 25, 60]],

    'camels' => [['Young camels for sale', 700, 1200], ['Milking camel with calf', 1200, 1800], ['Camels for Eid, healthy', 800, 1400], ['Strong male camel', 1000, 1600]],
    'cattle' => [['Dairy cows, good milk', 500, 900], ['Bulls for meat', 450, 800], ['Young calves', 150, 300]],
    'goats-sheep' => [['Somali goats, healthy', 60, 120], ['Black head sheep for Eid', 80, 150], ['Goats, 20 heads', 1200, 2200], ['Fat sheep for weddings', 90, 160]],
    'poultry' => [['Local chickens', 6, 12], ['Layers, 100 birds', 450, 700], ['Chicks one week old', 1, 3]],
    'pets' => [['Persian cat, friendly', 60, 150], ['Parrot that talks', 80, 200], ['German Shepherd puppy', 200, 400]],

    'farm-machinery' => [['Tractor Massey Ferguson', 9000, 16000], ['Water pump for farm', 300, 600], ['Plough for tractor', 600, 1200]],
    'seeds-plants' => [['Mango seedlings', 2, 5], ['Lemon trees, young', 3, 7], ['Maize seeds 25 kg', 30, 60]],
    'food-drinks' => [['Fresh bananas, wholesale', 10, 25], ['Camel milk, daily delivery', 2, 4], ['Somali honey, pure', 15, 35], ['Dates from Saudi, 5 kg', 15, 30]],

    'building-repair' => [['Building and painting houses', 0, 0], ['Plumber, fast and clean work', 0, 0], ['Electrician for homes and shops', 0, 0], ['Tiles and gypsum work', 0, 0]],
    'cleaning' => [['House cleaning service', 20, 50], ['Office cleaning, weekly', 80, 200]],
    'transport-moving' => [['Moving houses with truck', 50, 150], ['Airport taxi Aden Adde', 15, 30]],
    'computer-it' => [['Phone and laptop repair', 0, 0], ['Website design for business', 150, 600], ['CCTV installation', 0, 0]],
    'events' => [['Wedding hall decoration', 300, 900], ['Event tents and chairs for rent', 100, 400], ['Wedding photography and video', 200, 600]],
    'tutoring' => [['English classes, all levels', 20, 50], ['Maths and science tutor', 30, 60], ['Computer course for beginners', 25, 60]],
    'other-services' => [['Translation English–Somali', 10, 40], ['Tailor, men and women clothes', 0, 0], ['Car wash, home service', 5, 15]],

    'job-vacancies' => [['Shop assistant needed, Hodan', 200, 300], ['Driver with licence wanted', 250, 400], ['Accountant, 2 years experience', 450, 800], ['Teacher for primary school', 300, 500], ['Security guard, night shift', 180, 280], ['Sales person for phone shop', 200, 350], ['Nurse for private clinic', 400, 700], ['Cook for restaurant', 250, 400]],
    'looking-for-work' => [['Driver looking for work', 0, 0], ['Cleaner looking for job', 0, 0], ['IT graduate looking for work', 0, 0], ['Experienced mechanic', 0, 0]],

    'beauty' => [['Henna artist for weddings', 20, 80], ['Perfumes and uunsi, original', 10, 40], ['Hair products, wholesale', 15, 50], ['Skin care set, new', 20, 45]],
    'health' => [['Blood pressure machine', 20, 40], ['Wheelchair, good condition', 60, 120], ['Glucose meter with strips', 15, 30]],

    'kids-clothing' => [['Kids clothes for Eid', 8, 20], ['School uniforms, all sizes', 6, 15]],
    'toys' => [['Kids bicycle with wheels', 35, 70], ['Toy car with remote', 15, 35], ['Lego set, big box', 25, 60]],
    'baby-gear' => [['Baby stroller, like new', 40, 90], ['Baby cot with mattress', 50, 110], ['Car seat for baby', 30, 60]],

    'sports-equipment' => [['Football boots, size 43', 25, 50], ['Gym weights set 50 kg', 80, 160], ['Footballs, new', 10, 25], ['Treadmill for home', 300, 550]],
    'bicycles' => [['Mountain bike 26 inch', 80, 160], ['City bicycle, good brakes', 60, 110]],
    'books-games' => [['Quran with tafsir, new', 15, 35], ['School books grade 8', 10, 25], ['Chess and board games', 8, 20]],

    'building-materials' => [['Cement 50 kg bags', 7, 10], ['Iron bars 12 mm', 8, 14], ['Building blocks, delivery', 0, 1], ['Floor tiles, many colours', 8, 20], ['Roofing iron sheets', 6, 12]],
    'tools' => [['Bosch drill machine', 60, 120], ['Welding machine', 150, 300], ['Tool box full set', 40, 90], ['Ladder 6 metres', 50, 100]],
    'solar-generators' => [['Solar panel 300W, new', 90, 150], ['Generator 5 kVA, diesel', 700, 1200], ['Solar system for home, full set', 450, 900], ['Inverter 3 kW with batteries', 400, 750], ['Solar water pump', 300, 600]],

    'other-items' => [['Office printer HP', 90, 180], ['Big water tank 2000 L', 120, 220], ['Second hand items, cheap', 5, 50]],
];
