<?php

/*
| Demo ad texts by category slug: [title, lowest price, highest price (USD), photo words].
| Photo words pick photos of the same kind (tools/download-demo-photos); none = no photo.
| Prices are rough Mogadishu prices. Field values (brand, year...) are filled
| by the DemoSeeder; a brand in the title is used for the Brand field.
*/

return [
    'cars' => [
        ['Toyota Vitz 2012, automatic', 4500, 6500, 'toyota vitz'], ['Toyota Corolla 2010, clean', 5500, 8000, 'toyota corolla'], ['Toyota Land Cruiser V8 2015', 38000, 52000, 'toyota land cruiser'],
        ['Toyota Hilux double cabin 2016', 22000, 30000, 'toyota hilux'], ['Toyota Noah 2011, 8 seats', 7000, 9500, 'toyota noah van'], ['Suzuki Swift 2014', 4800, 6500, 'suzuki swift'],
        ['Nissan X-Trail 2013', 9000, 12500, 'nissan x-trail'], ['Toyota Mark X 2010', 7500, 10000, 'toyota mark x'], ['Mitsubishi Pajero 2008', 8000, 11000, 'mitsubishi pajero'],
        ['Honda Fit 2013, hybrid', 5000, 7000, 'honda fit'], ['Toyota Harrier 2012', 12000, 15500, 'toyota harrier'], ['Mazda Demio 2011', 3800, 5200, 'mazda demio'],
        ['Hyundai Tucson 2015', 11000, 14000, 'hyundai tucson'], ['Toyota Probox 2014', 4200, 5800, 'toyota probox'], ['Land Rover Discovery 4', 25000, 33000, 'land rover discovery'],
        ['Toyota Surf 2005, diesel', 9500, 13000, 'toyota 4runner'], ['Toyota Premio 2011', 7000, 9000, 'toyota premio'], ['Nissan Note 2012', 4000, 5500, 'nissan note'],
    ],
    'motorcycles' => [['Bajaj RE three-wheeler 2020', 2200, 3200, 'auto rickshaw'], ['Bajaj Boxer 150', 800, 1200], ['TVS King tuk-tuk, good engine', 1800, 2800, 'tuk tuk'], ['Yamaha motorcycle 125cc', 900, 1500, 'yamaha motorcycle'], ['Honda CG 125', 750, 1100, 'honda cg125'], ['Bajaj Pulsar 180', 1200, 1700, 'bajaj pulsar']],
    'trucks-buses' => [['Isuzu NPR truck 2009', 14000, 19000, 'isuzu truck'], ['Toyota Coaster bus 30 seats', 16000, 24000, 'toyota coaster bus'], ['Mitsubishi Canter tipper', 12000, 17000, 'mitsubishi canter'], ['Isuzu water tanker', 18000, 25000, 'water tanker truck']],
    'vehicle-parts' => [['Toyota tyres 195/65 R15, set of 4', 180, 320, 'car tyres'], ['Car battery 70Ah, new', 70, 120], ['Land Cruiser headlights', 90, 180], ['Engine oil 5L, original', 25, 45], ['Alloy wheels 17 inch', 250, 450, 'alloy wheel']],

    'houses-for-rent' => [['2 bedroom apartment in Hodan', 250, 450, 'apartment building africa'], ['3 bedroom house with garden, Wadajir', 400, 700, 'house with garden'], ['Furnished flat near KM4', 500, 900, 'furnished apartment living room'], ['1 bedroom studio, Hamar Weyne', 150, 250, 'bedroom apartment interior'], ['Big villa for rent, Daynile road', 900, 1600, 'villa house'], ['4 bedroom house, Howlwadaag', 600, 1000, 'house exterior']],
    'houses-for-sale' => [['3 bedroom house for sale, Hodan', 45000, 85000, 'house exterior'], ['New villa with parking, Wadajir', 120000, 220000, 'modern villa'], ['Old house on 20x20 plot, Shibis', 35000, 60000, 'old house'], ['2 storey house, Dharkenley', 90000, 150000, 'two storey house'], ['Apartment in new building, Waberi', 40000, 70000]],
    'land' => [['Plot 20x20 near Afgooye road', 6000, 15000, 'vacant lot'], ['Land 40x40 with title deed, Garasbaley', 18000, 35000, 'empty field'], ['Corner plot, Kaxda district', 9000, 20000, 'vacant lot'], ['Farm land 2 hectares, Lower Shabelle', 15000, 40000, 'farmland'], ['Plot 15x30 near the main road', 7000, 14000, 'empty field']],
    'commercial-property' => [['Shop for rent, Bakara market', 300, 700, 'shop front'], ['Office space 120 m², KM4', 600, 1200, 'empty office space'], ['Warehouse 300 m² for rent', 900, 1800, 'warehouse interior'], ['Restaurant space, Lido area', 800, 1500, 'restaurant interior']],

    'mobile-phones' => [['Samsung Galaxy A54, 128 GB', 260, 340, 'samsung galaxy phone'], ['iPhone 13, 128 GB, clean', 420, 520, 'iphone'], ['iPhone 11, 64 GB', 230, 300, 'iphone'], ['Tecno Spark 20, new in box', 110, 150, 'android smartphone'], ['Samsung Galaxy S21 Ultra', 380, 480, 'samsung galaxy phone'], ['Infinix Hot 40', 120, 160, 'android smartphone'], ['iPhone 14 Pro Max 256 GB', 750, 900, 'iphone'], ['Xiaomi Redmi Note 13', 170, 220], ['Nokia 105, new', 15, 25, 'nokia mobile phone'], ['Samsung Galaxy A15', 140, 180, 'samsung galaxy phone']],
    'tablets' => [['iPad 9th generation, 64 GB', 220, 290, 'ipad'], ['Samsung Galaxy Tab A8', 160, 210, 'android tablet'], ['Lenovo tablet for kids', 80, 120, 'android tablet']],
    'phone-accessories' => [['Wireless earbuds, new', 15, 35, 'earbuds'], ['Power bank 20000 mAh', 18, 30, 'power bank'], ['Fast charger 25W, original', 10, 20, 'phone charger'], ['Phone cases, all models', 3, 8, 'phone cases']],
    'laptops-computers' => [['HP EliteBook 840 G5, Core i5', 280, 380, 'hp laptop'], ['Dell Latitude 7490, 16 GB RAM', 300, 420, 'dell laptop'], ['MacBook Air M1, 256 GB', 600, 750, 'macbook air'], ['Lenovo ThinkPad T480', 260, 340, 'thinkpad laptop'], ['Desktop computer + monitor', 200, 320, 'desktop computer monitor'], ['Asus gaming laptop RTX 3050', 650, 850, 'laptop computer']],
    'tv-audio' => [['Samsung 55 inch smart TV', 380, 480, 'flat screen tv'], ['LG 43 inch TV, like new', 220, 290, 'flat screen tv'], ['Sound system with speakers', 120, 220, 'loudspeakers'], ['Satellite dish and receiver', 40, 80, 'satellite dish']],
    'cameras' => [['Canon EOS 2000D with lens', 330, 420, 'canon dslr camera'], ['Security cameras, 4 set', 150, 260, 'security camera cctv'], ['Nikon D5300 camera', 300, 380, 'nikon dslr']],
    'other-electronics' => [['Bluetooth speaker JBL', 45, 90, 'bluetooth speaker'], ['PlayStation 4 with 2 controllers', 220, 300, 'playstation 4'], ['Wi-Fi router, 4G', 40, 70, 'wifi router']],

    'furniture' => [['Sofa set 7 seater', 450, 800, 'sofa set'], ['Wooden bed 6x6 with mattress', 250, 420, 'bed frame'], ['Dining table with 6 chairs', 300, 550, 'dining table chairs'], ['Office desk and chair', 120, 220, 'office desk chair'], ['Wardrobe 3 doors', 180, 320], ['Plastic chairs, 20 pieces', 60, 100]],
    'home-appliances' => [['Samsung fridge 300 L', 280, 420, 'fridge kitchen'], ['Washing machine LG 7 kg', 250, 380, 'washing machine'], ['Split air conditioner 1.5 HP', 300, 450, 'air conditioner unit'], ['Water dispenser hot and cold', 70, 120], ['Standing fan, new', 25, 45, 'desk fan']],
    'kitchen' => [['Cooking pots set, 10 pieces', 35, 70, 'saucepans'], ['Gas cooker 4 burners', 120, 220, 'gas stove'], ['Blender and juicer', 25, 50, 'kitchen blender'], ['Big aluminium pots for events', 40, 90, 'large cooking pot']],

    'mens-clothing' => [['Macawis, new, all colours', 8, 18, 'sarong fabric'], ['Men suit, 2 pieces', 60, 120], ['Khamis for men', 12, 25, 'kaftan'], ['Jeans and shirts, bundle', 20, 45, 'denim jeans']],
    'womens-clothing' => [['Dirac with garbasaar, new', 25, 60, 'colorful dress fabric'], ['Abaya, many sizes', 20, 45], ['Wedding dress, used once', 120, 300], ['Hijab bundle, 10 pieces', 15, 30, 'scarves']],
    'shoes' => [['Nike sports shoes, size 42', 35, 70, 'sneakers'], ['Leather shoes for men', 25, 50, 'leather shoes'], ['Women sandals, new', 10, 25, 'sandals']],
    'bags' => [['Women handbag, new', 15, 40, 'handbag'], ['School backpack', 8, 18, 'backpack'], ['Travel suitcase, big', 35, 70, 'luggage']],
    'watches-jewellery' => [['Gold necklace 18k', 400, 1200, 'gold necklace'], ['Casio watch, original', 30, 70, 'wrist watch'], ['Silver ring set', 20, 60, 'silver rings'], ['Smart watch, new', 25, 60, 'smartwatch']],

    'camels' => [['Young camels for sale', 700, 1200, 'camels'], ['Milking camel with calf', 1200, 1800, 'camel calf'], ['Camels for Eid, healthy', 800, 1400, 'camels'], ['Strong male camel', 1000, 1600, 'camel']],
    'cattle' => [['Dairy cows, good milk', 500, 900, 'dairy cows'], ['Bulls for meat', 450, 800], ['Young calves', 150, 300, 'calves']],
    'goats-sheep' => [['Somali goats, healthy', 60, 120, 'goats'], ['Black head sheep for Eid', 80, 150, 'sheep farm'], ['Goats, 20 heads', 1200, 2200], ['Fat sheep for weddings', 90, 160, 'sheep']],
    'poultry' => [['Local chickens', 6, 12, 'hens'], ['Layers, 100 birds', 450, 700, 'chicken coop'], ['Chicks one week old', 1, 3]],
    'pets' => [['Persian cat, friendly', 60, 150, 'persian cat'], ['Parrot that talks', 80, 200, 'parrot'], ['German Shepherd puppy', 200, 400, 'german shepherd puppy']],

    'farm-machinery' => [['Tractor Massey Ferguson', 9000, 16000, 'massey ferguson tractor'], ['Water pump for farm', 300, 600], ['Plough for tractor', 600, 1200]],
    'seeds-plants' => [['Mango seedlings', 2, 5, 'mango seedlings'], ['Lemon trees, young', 3, 7, 'lemon tree'], ['Maize seeds 25 kg', 30, 60, 'corn kernels']],
    'food-drinks' => [['Fresh bananas, wholesale', 10, 25, 'bananas'], ['Camel milk, daily delivery', 2, 4, 'milk bottles'], ['Somali honey, pure', 15, 35, 'honey jar'], ['Dates from Saudi, 5 kg', 15, 30, 'dates palm fruit']],

    'building-repair' => [['Building and painting houses', 0, 0], ['Plumber, fast and clean work', 0, 0], ['Electrician for homes and shops', 0, 0], ['Tiles and gypsum work', 0, 0]],
    'cleaning' => [['House cleaning service', 20, 50, 'cleaning supplies'], ['Office cleaning, weekly', 80, 200, 'cleaning supplies']],
    'transport-moving' => [['Moving houses with truck', 50, 150, 'moving boxes'], ['Airport taxi Aden Adde', 15, 30, 'taxi car']],
    'computer-it' => [['Phone and laptop repair', 0, 0], ['Website design for business', 150, 600, 'web design laptop'], ['CCTV installation', 0, 0]],
    'events' => [['Wedding hall decoration', 300, 900, 'wedding hall decoration'], ['Event tents and chairs for rent', 100, 400, 'event tent chairs'], ['Wedding photography and video', 200, 600]],
    'tutoring' => [['English classes, all levels', 20, 50, 'notebook pen books'], ['Maths and science tutor', 30, 60, 'math notebook'], ['Computer course for beginners', 25, 60, 'computer keyboard']],
    'other-services' => [['Translation English–Somali', 10, 40], ['Tailor, men and women clothes', 0, 0], ['Car wash, home service', 5, 15]],

    'job-vacancies' => [['Shop assistant needed, Hodan', 200, 300], ['Driver with licence wanted', 250, 400], ['Accountant, 2 years experience', 450, 800], ['Teacher for primary school', 300, 500], ['Security guard, night shift', 180, 280], ['Sales person for phone shop', 200, 350], ['Nurse for private clinic', 400, 700], ['Cook for restaurant', 250, 400]],
    'looking-for-work' => [['Driver looking for work', 0, 0], ['Cleaner looking for job', 0, 0], ['IT graduate looking for work', 0, 0], ['Experienced mechanic', 0, 0]],

    'beauty' => [['Henna artist for weddings', 20, 80, 'henna hands'], ['Perfumes and uunsi, original', 10, 40, 'perfume'], ['Hair products, wholesale', 15, 50, 'hair products'], ['Skin care set, new', 20, 45, 'skin care products']],
    'health' => [['Blood pressure machine', 20, 40, 'blood pressure monitor'], ['Wheelchair, good condition', 60, 120], ['Glucose meter with strips', 15, 30]],

    'kids-clothing' => [['Kids clothes for Eid', 8, 20, 'childrens clothes rack'], ['School uniforms, all sizes', 6, 15]],
    'toys' => [['Kids bicycle with wheels', 35, 70, 'childrens bike'], ['Toy car with remote', 15, 35, 'remote control car'], ['Lego set, big box', 25, 60, 'lego bricks']],
    'baby-gear' => [['Baby stroller, like new', 40, 90], ['Baby cot with mattress', 50, 110, 'baby crib'], ['Car seat for baby', 30, 60]],

    'sports-equipment' => [['Football boots, size 43', 25, 50, 'football boots'], ['Gym weights set 50 kg', 80, 160, 'dumbbells'], ['Footballs, new', 10, 25, 'soccer ball'], ['Treadmill for home', 300, 550, 'treadmill machine']],
    'bicycles' => [['Mountain bike 26 inch', 80, 160, 'mtb bicycle'], ['City bicycle, good brakes', 60, 110, 'bicycle']],
    'books-games' => [['Quran with tafsir, new', 15, 35, 'quran book'], ['School books grade 8', 10, 25, 'textbooks'], ['Chess and board games', 8, 20, 'chess board']],

    'building-materials' => [['Cement 50 kg bags', 7, 10], ['Iron bars 12 mm', 8, 14, 'steel rebar'], ['Building blocks, delivery', 0, 1], ['Floor tiles, many colours', 8, 20, 'floor tiles'], ['Roofing iron sheets', 6, 12, 'corrugated metal']],
    'tools' => [['Bosch drill machine', 60, 120, 'cordless drill'], ['Welding machine', 150, 300, 'welding machine'], ['Tool box full set', 40, 90, 'tool box'], ['Ladder 6 metres', 50, 100, 'ladder']],
    'solar-generators' => [['Solar panel 300W, new', 90, 150, 'solar panel'], ['Generator 5 kVA, diesel', 700, 1200, 'diesel generator'], ['Solar system for home, full set', 450, 900, 'rooftop solar'], ['Inverter 3 kW with batteries', 400, 750, 'inverter batteries'], ['Solar water pump', 300, 600]],

    'other-items' => [['Office printer HP', 90, 180, 'office printer'], ['Big water tank 2000 L', 120, 220], ['Second hand items, cheap', 5, 50]],
];
