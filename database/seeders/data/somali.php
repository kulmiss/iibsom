<?php

/*
| Somali names for the starting catalogue (categories, fields, places).
| English text => Somali text. Needs native review like lang/so.
| Seeders only ADD a missing Somali name; they never change names an admin edited.
*/

return [
    // Categories
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
];
