<?php

/*
| Somali names for the starting catalogue (categories, fields, places).
| English text => Somali text. Needs native review like lang/so.
| Seeders only ADD a missing Somali name; they never change names an admin edited.
*/

return [
    // Categories
    // Jiji round (PR 65): car body type
    'Body type' => 'Nooca jirka', 'Saloon' => 'Saloon', 'Hatchback' => 'Hatchback', 'SUV' => 'SUV', 'Pickup' => 'Bikab',
    'Van' => 'Faan', 'Station wagon' => 'Istaanshan', 'Coupe' => 'Kuube',
    // Round 2 (PR 52): car colours
    'Colour' => 'Midabka', 'White' => 'Caddaan', 'Black' => 'Madow', 'Silver' => 'Silfar', 'Grey' => 'Cawl', 'Blue' => 'Buluug', 'Red' => 'Casaan', 'Green' => 'Cagaar', 'Brown' => 'Bunni',
    // Round 2 (PR 50): fuller category tree
    'Trucks' => 'Gaari xamuul', 'Buses & Minibuses' => 'Bas & Mini-bas', 'Seats' => 'Kuraasta',
    'Vehicle Parts & Accessories' => 'Qalabka & Agabka gaadiidka', 'Part type' => 'Nooca qalabka', 'Boats' => 'Doonyo',
    'Engine & Gearbox' => 'Matoor & Giirboks', 'Wheels & Tyres' => 'Shaagag & Taayiro', 'Car Care' => 'Daryeelka gaariga',
    'Oils & Fluids' => 'Saliid & Dareere', 'Lights & Electrics' => 'Nalal & Koronto', 'Body Parts' => 'Qaybaha jirka gaariga',
    'Batteries' => 'Baytariyo', 'Car Audio & Screens' => 'Codka & Shaashadda gaariga', 'Other Parts' => 'Qalab kale',
    'Rooms for Rent' => 'Qolal kiro ah', 'Computer Accessories' => 'Agabka kombiyuutarka', 'Home Decor & Carpets' => 'Qurxinta guriga & Roogag',
    'Traditional Wear' => 'Dharka dhaqanka', 'Cows' => "Lo'", 'Goats' => 'Riyo', 'Sheep' => 'Ido',
    'Animal Feed & Supplies' => 'Calafka & Agabka xoolaha', 'Car Repair' => 'Dayactirka gaariga',
    'Phone & Electronics Repair' => 'Dayactirka taleefanka & elektaroonigga', 'Beauty & Salon' => 'Qurxin & Saloon',
    'Perfume, Oud & Incense' => 'Cadar, Cuud & Uunsi', 'Plumbing & Electrical' => 'Tuubbooyin & Koronto',
    'Vehicles' => 'Gaadiid', 'Cars' => 'Baabuur', 'Motorcycles & Bajaj' => 'Mooto & Bajaaj', 'Trucks & Buses' => 'Gaari xamuul & Bas',
    'Vehicle Parts' => 'Qalabka gaadiidka', 'Property' => 'Guryo & Dhul', 'Houses & Apartments for Rent' => 'Guryo & Abaartamenti kiro ah',
    'Houses & Apartments for Sale' => 'Guryo & Abaartamenti iib ah', 'Land & Plots' => 'Dhul & Boos', 'Shops & Offices' => 'Dukaamo & Xafiisyo',
    'Phones & Tablets' => 'Taleefanno & Tablet', 'Mobile Phones' => 'Taleefannada gacanta', 'Tablets' => 'Tablet', 'Phone Accessories' => 'Qalabka taleefanka',
    'Electronics' => 'Elektaroonig', 'Laptops & Computers' => 'Laabtob & Kombiyuutar', 'TV & Audio' => 'TV & Cod', 'Cameras' => 'Kamarado',
    'Other Electronics' => 'Elektaroonig kale', 'Home & Furniture' => 'Guriga & Alaabta', 'Furniture' => 'Alaabta guriga', 'Home Appliances' => 'Qalabka korontada guriga',
    'Kitchen' => 'Jikada', 'Fashion' => 'Dhar & Moodo', "Men's Clothing" => 'Dharka ragga', "Women's Clothing" => 'Dharka haweenka', 'Shoes' => 'Kabo',
    'Bags' => 'Boorsooyin', 'Watches & Jewellery' => 'Saacado & Dahab', 'Livestock & Animals' => 'Xoolo & Xayawaan', 'Camels' => 'Geel', 'Cattle' => "Lo'",
    'Goats & Sheep' => 'Ari (riyo & ido)', 'Poultry' => 'Digaag', 'Pets' => 'Xayawaanka guriga', 'Agriculture & Food' => 'Beero & Cunto',
    'Farm Machinery' => 'Mashiinada beeraha', 'Seeds & Plants' => 'Abuur & Geedo', 'Food & Drinks' => 'Cunto & Cabitaan', 'Services' => 'Adeegyo',
    'Building & Repair' => 'Dhisme & Dayactir', 'Cleaning' => 'Nadiifin', 'Transport & Moving' => 'Gaadiid & Guurid', 'Computer & IT' => 'Kombiyuutar & IT',
    'Events & Weddings' => 'Munaasabado & Arooso', 'Classes & Tutoring' => 'Casharro & Waxbarid', 'Other Services' => 'Adeegyo kale', 'Jobs' => 'Shaqooyin',
    'Job Vacancies' => 'Shaqooyin bannaan', 'Looking for Work' => 'Shaqo raadis', 'Health & Beauty' => 'Caafimaad & Qurux', 'Beauty & Cosmetics' => 'Qurux & Isqurxin',
    'Health & Medical' => 'Caafimaad & Daawo', 'Babies & Kids' => 'Carruur', "Kids' Clothing" => 'Dharka carruurta', 'Toys' => 'Alaabta ciyaarta',
    'Baby Gear' => 'Qalabka ilmaha', 'Sports & Hobbies' => 'Isboorti & Hiwaayad', 'Sports Equipment' => 'Qalabka isboortiga', 'Bicycles' => 'Baaskiil',
    'Books & Games' => 'Buugaag & Ciyaaro', 'Construction & Tools' => 'Dhisme & Qalab', 'Building Materials' => 'Agabka dhismaha', 'Tools' => 'Qalab',
    'Solar & Generators' => 'Solar & Matoor', 'Everything Else' => 'Wax kale', 'Other Items' => 'Alaab kale',

    // Fields and choices
    'Brand' => 'Shirkadda', 'Model' => 'Moodel', 'Year' => 'Sannadka', 'Kilometres' => 'Kiilomitir', 'Fuel' => 'Shidaal', 'Gear' => 'Giir',
    'Bedrooms' => 'Qolal jiif', 'Bathrooms' => 'Musqulo', 'Furnished' => 'Alaab leh', 'Size' => 'Cabbir', 'How many' => 'Imisa', 'Job type' => 'Nooca shaqada',
    'Storage' => 'Kaydin', 'RAM' => 'RAM', 'Petrol' => 'Baansiin', 'Diesel' => 'Naafto', 'Hybrid' => 'Haybrid', 'Electric' => 'Koronto',
    'Automatic' => 'Otomaatig', 'Manual' => 'Gacan', 'Full-time' => 'Waqti buuxa', 'Part-time' => 'Waqti dhiman', 'Contract' => 'Qandaraas',
    'Temporary' => 'Ku-meel-gaar', 'Other' => 'Kale',

    // Places (Somali spelling)
    'Banadir' => 'Banaadir', 'Mogadishu' => 'Muqdisho', 'Lower Shabelle' => 'Shabeellaha Hoose', 'Middle Shabelle' => 'Shabeellaha Dhexe',
    'Hiiraan' => 'Hiiraan', 'Galgaduud' => 'Galgaduud', 'Mudug' => 'Mudug', 'Nugaal' => 'Nugaal', 'Bari' => 'Bari', 'Bay' => 'Baay', 'Bakool' => 'Bakool',
    'Gedo' => 'Gedo', 'Lower Juba' => 'Jubbada Hoose', 'Middle Juba' => 'Jubbada Dhexe', 'Afgooye' => 'Afgooye', 'Marka' => 'Marka', 'Jowhar' => 'Jowhar',
    'Balcad' => 'Balcad', 'Beledweyne' => 'Beledweyne', 'Dhusamareb' => 'Dhuusamareeb', 'Guriceel' => 'Guriceel', 'Galkayo' => 'Gaalkacyo', 'Hobyo' => 'Hobyo',
    'Garowe' => 'Garoowe', 'Bosaso' => 'Boosaaso', 'Qardho' => 'Qardho', 'Baidoa' => 'Baydhabo', 'Hudur' => 'Xuddur', 'Garbahaarey' => 'Garbahaarrey',
    'Luuq' => 'Luuq', 'Kismayo' => 'Kismaayo', 'Jilib' => 'Jilib',
    'Abdiaziz' => 'Cabdicasiis', 'Bondhere' => 'Boondheere', 'Daynile' => 'Dayniile', 'Dharkenley' => 'Dharkeenley', 'Garasbaley' => 'Garasbaaley',
    'Hamar Jajab' => 'Xamar Jajab', 'Hamar Weyne' => 'Xamar Weyne', 'Heliwa' => 'Heliwaa', 'Hodan' => 'Hodan', 'Howlwadaag' => 'Hawl-wadaag',
    'Kaaraan' => 'Kaaraan', 'Kaxda' => 'Kaxda', 'Shangani' => 'Shingaani', 'Shibis' => 'Shibis', 'Waberi' => 'Waaberi', 'Wadajir' => 'Wadajir',
    'Wardhigley' => 'Wardhiigley', 'Yaqshid' => 'Yaaqshiid',
    // PR 111: Hargeisa and other big cities (starting list, needs native review).
    'Maroodi Jeex' => 'Maroodi Jeex', 'Hargeisa' => 'Hargeysa', 'Togdheer' => 'Togdheer', 'Burao' => 'Burco', 'Sahil' => 'Saaxil',
    'Berbera' => 'Berbera', 'Awdal' => 'Awdal', 'Borama' => 'Boorama',
    '26 June' => '26 Juun', 'Ahmed Dhagah' => 'Axmed Dhagax', 'Gacan Libah' => 'Gacan Libaax', 'Ibrahim Koodbuur' => 'Ibraahim Koodbuur',
    'Mohamed Haybe' => 'Maxamed Haybe', 'Mohamed Mooge' => 'Maxamed Mooge',

    // PR 93: more categories (owner: "keep ours and add theirs"). Needs native review.
    'Heavy Machinery' => 'Mashiinada culus', 'Land & Plots for Rent' => 'Dhul & Boos kiro ah', 'Short Stays' => 'Hoy muddo gaaban',
    'New Builds' => 'Guryo cusub', 'Halls & Event Venues' => 'Hoolal & Goobaha munaasabadaha', 'Warehouses & Stores' => 'Bakhaarro',
    'Smart Watches' => 'Saacadaha casriga ah', 'Headphones & Earbuds' => 'Dhegaha-dhegeysiga', 'Video Games & Consoles' => 'Ciyaaraha fiidyowga',
    'Security Cameras & Alarms' => 'Kamaradaha amniga & Alaarmiyo', 'Networking & Internet' => 'Shabakad & Internet', 'Printers & Scanners' => 'Daabacayaal & Iskaanar',
    'Computer Monitors' => 'Shaashadaha kombiyuutarka', 'Computer Parts' => 'Qaybaha kombiyuutarka', 'Software' => 'Barnaamijyo',
    'Lighting' => 'Nalal', 'Storage & Shelves' => 'Kaydin & Khaanado', 'Kitchen Appliances' => 'Qalabka korontada jikada', 'Cleaning Products' => 'Alaabta nadaafadda',
    'Garden Supplies' => 'Agabka beerta guriga', 'Water Tanks & Pumps' => 'Haamo biyo & Matoorro biyo',
    'Dogs' => 'Eeyo', 'Cats' => 'Bisado', 'Birds' => 'Shimbiro', 'Fish & Aquariums' => 'Kalluun & Haamaha kalluunka', 'Pet Accessories' => 'Agabka xayawaanka guriga',
    'Fertilizers & Farm Supplies' => 'Bacrimiye & Agabka beeraha',
    'Printing & Design' => 'Daabacaad & Naqshad', 'Delivery & Logistics' => 'Gaarsiin & Saadka', 'Legal Services' => 'Adeegyada sharciga',
    'Accounting & Tax' => 'Xisaabaad & Canshuur', 'Recruitment' => 'Shaqaale-qorid', 'Equipment Rental' => 'Kirada qalabka',
    'Drivers & Airport Transfers' => 'Darawallo & Gaarsiinta garoonka', 'Travel & Tours' => 'Safar & Dalxiis', 'Childcare' => 'Daryeelka carruurta',
    'Fitness & Personal Training' => 'Jimicsi & Tababar', 'Catering' => 'Cunto-bixin', 'Photography & Video' => 'Sawir & Fiidyow',
    'DJ & Entertainment' => 'DJ & Madadaalo', 'Gardening & Landscaping' => 'Beer-qurxin', 'Pet Care' => 'Daryeelka xayawaanka guriga',
    'Manufacturing & Workshops' => 'Warshado & Wershado-yar',
    'Hair Care' => 'Daryeelka timaha', 'Skin & Body Care' => 'Daryeelka maqaarka & jirka', 'Makeup' => 'Isqurxin (makeup)',
    'Vitamins & Supplements' => 'Fiitamiinno & Kaabis', 'Medical Supplies' => 'Agabka caafimaadka',
    "Kids' Shoes" => 'Kabaha carruurta', "Children's Furniture" => 'Alaabta carruurta', 'Feeding & Baby Care' => 'Quudin & Daryeelka ilmaha',
    'Maternity' => 'Hooyada uurka leh', 'Car Seats & Safety' => 'Kuraasta gaariga & Badbaadada', 'Playground Equipment' => 'Qalabka garoonka ciyaarta',
    'Musical Instruments' => 'Qalabka muusikada', 'Arts & Crafts' => 'Farshaxan & Gacan-ku-samays', 'Camping & Outdoor' => 'Kaam & Bannaanka',
    'Scooters & E-bikes' => 'Iskuutar & Baaskiil koronto',
    'Power Tools' => 'Qalabka korontada shaqada', 'Measuring Tools' => 'Qalabka cabbirka', 'Hardware & Fasteners' => 'Biraha & Musmaarro',
    'Doors, Locks & Gates' => 'Albaabbo, Qufulo & Irdo', 'Windows & Glass' => 'Daaqado & Muraayad',
    'Commercial Equipment' => 'Qalabka ganacsiga', 'Medical Equipment' => 'Qalabka caafimaadka', 'Safety Gear' => 'Qalabka badbaadada',
    'Factory Machines' => 'Mashiinada warshadaha', 'Shop Equipment' => 'Qalabka dukaanka', 'Restaurant & Catering Equipment' => 'Qalabka maqaayadda',
    'Office Supplies & Stationery' => 'Agabka xafiiska & Qoraalka', 'Salon Equipment' => 'Qalabka saloonka', 'Printing Machines' => 'Mashiinada daabacaadda',
    'Stage & Event Equipment' => 'Qalabka masraxa & munaasabadaha',
    'Business & Industry' => 'Ganacsi & Warshad', 'Businesses for Sale' => 'Ganacsiyo iib ah', 'Import & Export' => 'Soo-dejin & Dhoofin',
    'Wholesale & Bulk' => 'Jumlad', 'Business Services' => 'Adeegyada ganacsiga',
    // Field of work (jobs)
    'Field of work' => 'Qaybta shaqada', 'Accounting & Finance' => 'Xisaabaad & Maaliyad', 'Administration & Office' => 'Maamul & Xafiis',
    'Advertising & Marketing' => 'Xayeysiin & Suuq-geyn', 'Arts, Media & Design' => 'Farshaxan, Warbaahin & Naqshad',
    'Cleaning & Housekeeping' => 'Nadaafad & Shaqada guriga', 'Construction & Skilled Trades' => 'Dhisme & Xirfado', 'Customer Service' => 'Adeegga macaamiisha',
    'Driving & Delivery' => 'Darawalnimo & Gaarsiin', 'Teaching & Education' => 'Macallinnimo & Waxbarasho', 'Engineering' => 'Injineernimo',
    'Farming & Livestock' => 'Beero & Xoolo', 'Healthcare & Nursing' => 'Caafimaad & Kalkaalin', 'Hotels, Restaurants & Catering' => 'Hoteello & Maqaayado',
    'Human Resources' => 'Shaqaalaha (HR)', 'IT & Technology' => 'IT & Tiknoolajiyad', 'Legal' => 'Sharci', 'Logistics & Transport' => 'Saad & Gaadiid',
    'Management' => 'Maareyn', 'Manufacturing & Production' => 'Wax-soo-saar', 'Aid & Development (NGO)' => 'Gargaar & Horumar (NGO)',
    'Research & Data' => 'Cilmi-baaris & Xog', 'Sales' => 'Iibin', 'Security' => 'Amni', 'Travel & Tourism' => 'Safar & Dalxiis', 'Internship' => 'Tababar shaqo (internship)',
];
