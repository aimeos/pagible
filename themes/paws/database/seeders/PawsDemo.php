<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Str;


/**
 * Paws theme demo for the fictional Alsterpark Vets clinic.
 */
class PawsDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'appointment' => 'Book a vet appointment at Alsterpark Vets in Hamburg online or by phone. New clients welcome, same-day slots for sick pets.',
        'careers' => 'Jobs at Alsterpark Vets in Hamburg: veterinarians, veterinary nurses and apprentices in a calm, well-equipped small animal clinic.',
        'cat-care' => 'Cat-friendly vet in Hamburg: a separate cat waiting room, quiet exam rooms and gentle handling at Alsterpark Vets.',
        'cat-travel-guide' => 'How to get your cat into the carrier and to the vet calmly, with tips from the team of Alsterpark Vets in Hamburg.',
        'dental-care' => 'Dental care for dogs and cats in Hamburg: dental checks, scaling and polishing under anaesthesia and dental X-rays.',
        'dental-guide' => 'How to brush your dog\'s or cat\'s teeth at home, step by step, and which signs mean a dental check is due.',
        'diagnostics' => 'In-house lab, digital X-ray and ultrasound at Alsterpark Vets in Hamburg, with most results during your visit.',
        'emergencies' => 'Pet emergency in Hamburg? Same-day appointments during opening hours and an emergency number day and night.',
        'guides' => 'Pet care guides from Alsterpark Vets in Hamburg: your puppy\'s first year, brushing teeth at home and calm trips to the vet with your cat.',
        'imprint' => 'Legal notice of Alsterpark Vets, Hamburg.',
        'new-clients' => 'New at Alsterpark Vets in Hamburg? Your first visit, what to bring, payment and pet insurance, and how to find us.',
        'privacy' => 'Privacy policy of Alsterpark Vets, Hamburg.',
        'puppy-guide' => 'Your puppy\'s first year: vaccinations, worming, microchip, neutering and the right food, explained by the vets of Alsterpark Vets.',
        'puppy-kitten-care' => 'Puppy and kitten care in Hamburg: first vaccinations, microchip, worming and advice for a healthy start.',
        'senior-pets' => 'Senior pet care in Hamburg: check-ups, blood tests, pain management and arthritis care for older dogs and cats.',
        'services' => 'Vaccinations, puppy and kitten care, dental care, surgery, diagnostics, cats, rabbits, senior pets and emergencies at Alsterpark Vets in Hamburg.',
        'small-pets' => 'Vet for rabbits, guinea pigs and other small pets in Hamburg, with dental checks, vaccinations and advice on housing and diet.',
        'surgery' => 'Soft tissue surgery and neutering for dogs, cats and rabbits in Hamburg, with modern anaesthesia monitoring and pain management.',
        'team' => 'Meet the vets and veterinary nurses of Alsterpark Vets in Hamburg, and the pets that come to work with them.',
        'vaccinations' => 'Check-ups and vaccinations for dogs, cats and rabbits in Hamburg, with a full health check at every visit.',
    ];

    /**
     * Curated Unsplash photos used by the veterinary clinic demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    protected const PHOTOS = [
        'cat-chin' => ['photo-1513360371669-4adf3dd7dff8', 'Happy cat', 'Cream-coloured cat enjoying a chin scratch'],
        'cat-exam' => ['photo-1733783489145-f3d3ee7a9ccf', 'Cat check-up', 'Vet in gloves gently holding a white cat on the exam table'],
        'cat-lap' => ['photo-1596272875729-ed2ff7d6d9c5', 'Cat on the lap', 'Silver tabby kitten held on its owner\'s lap'],
        'cat-portrait' => ['photo-1515002246390-7bf7e8f87b54', 'Curious cat', 'Close-up of a tabby cat looking to the side'],
        'cat-stroke' => ['photo-1628009368231-7bb7cfcb0def', 'Calm handling', 'Woman gently stroking a tabby cat by the window'],
        'chihuahua' => ['photo-1654895716780-b4664497420d', 'Exam room', 'Chihuahua standing on the exam table in a bright clinic room'],
        'dachshund-exam' => ['photo-1770836037793-95bdbf190f71', 'Senior check-up', 'Vet in a white coat examining a black dachshund'],
        'dental-exam' => ['photo-1770836037816-4445dbd449fd', 'Dental check', 'Vet with a head lamp looking at a dog\'s teeth'],
        'dog-hug' => ['photo-1522276498395-f4f68f7f8454', 'Best friends', 'Man hugging his happy golden dog outdoors'],
        'dog-injection' => ['photo-1770836037275-38b44e4b101f', 'Vaccination', 'Vet giving a small dog a vaccination'],
        'dog-kiss' => ['photo-1562505209-85d7688af8a7', 'Walk in the park', 'Woman kissing her small dog in a park'],
        'dog-smile' => ['photo-1711376582747-22cd0839ffad', 'Happy dog', 'Smiling white and brown dog being stroked'],
        'dog-treat' => ['photo-1632236542159-809925d85fc0', 'A treat for later', 'Dog lying on the floor while a hand offers a treat'],
        'golden-kiss' => ['photo-1632498301446-5f78baad40d0', 'Golden retriever', 'Woman kissing her golden retriever on a forest path'],
        'kitten-hands' => ['photo-1728013274420-ed02b1f58887', 'Kitten check-up', 'Hands holding a white kitten during an examination'],
        'lab' => ['photo-1602052577122-f73b9710adba', 'In-house lab', 'Laboratory with analysers and a microscope'],
        'puppy-golden' => ['photo-1591160690555-5debfba289f0', 'Golden puppy', 'Golden retriever puppy with a red collar lying on a table'],
        'puppy-hands' => ['photo-1577175889968-f551f5944abd', 'Puppy visit', 'Fluffy brown puppy held in the hands of its owner'],
        'rabbit' => ['photo-1529040181623-e04ebc611e25', 'Rabbit', 'Grey and white rabbit in front of a warm background'],
        'rabbit-window' => ['photo-1589952283406-b53a7d1347e8', 'Indoor rabbit', 'Brown rabbit sitting on a windowsill'],
        'surgery' => ['photo-1551076805-e1869033e561', 'Operating room', 'Bright operating room with a surgical table and lamp'],
        'team-lena' => ['photo-1594824476967-48c8b964273f', 'Dr. Lena Brandt', 'Vet in teal scrubs smiling with crossed arms'],
        'team-care' => ['photo-1700665537650-1bf37979aae0', 'Our team', 'Vet holding a small puppy and smiling at a colleague'],
        'team-nurse' => ['photo-1622253694238-3b22139576c6', 'Rafael Santos', 'Veterinary nurse in blue scrubs laughing'],
        'team-omid' => ['photo-1612349317150-e413f6a5b16d', 'Dr. Omid Karimi', 'Vet with glasses in a white coat and a stethoscope'],
        'team-priya' => ['photo-1659353888906-adb3e0041693', 'Dr. Priya Nair', 'Vet in a white coat in front of a red wall'],
        'team-work' => ['photo-1700665537604-412e89a285c3', 'Teamwork', 'Two vets in navy scrubs examining a dog together'],
        'vet-corgi' => ['photo-1644675272883-0c4d582528d8', 'Friendly welcome', 'Vet in light blue scrubs with a corgi on the exam table'],
    ];

    private string $element;
    private string $guidesId;
    /** @var array<string, string> Icon file IDs keyed by name */
    private array $icons = [];
    private string $logoFile;


    /**
     * Creates the appointment page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAppointment( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Appointment',
            'title' => 'Book a Vet Appointment | Alsterpark Vets Hamburg',
            'path' => 'appointment',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => 'appointment', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Book a visit',
                'description' => 'Tell us about your pet and when you are available. We confirm your appointment by phone or email within a few hours. If your pet is in pain, bleeding or has trouble breathing, please call us instead, we see emergencies straight away.',
                'inputs' => [
                    ['field' => 'name', 'required' => true, 'input' => 'text'],
                    ['field' => 'telephone', 'required' => true, 'input' => 'text'],
                    ['field' => 'email', 'required' => true, 'input' => 'text'],
                    ['field' => 'Your pet', 'required' => true, 'input' => 'select', 'options' => "Dog\nCat\nRabbit\nGuinea pig or other small pet"],
                    ['field' => 'Pet\'s name and age', 'required' => true, 'input' => 'text'],
                    ['field' => 'Reason for the visit', 'required' => true, 'input' => 'select', 'options' => "Check-up and vaccination\nPuppy or kitten visit\nSick or injured\nDental check\nNeutering or surgery\nSenior check-up\nSecond opinion"],
                    ['field' => 'Client', 'required' => true, 'input' => 'select', 'options' => "New client\nExisting client"],
                    ['field' => 'Preferred time', 'required' => false, 'input' => 'select', 'options' => "Morning\nAfternoon\nEvening\nSaturday"],
                ],
            ]],
            $this->map( 'How to find us' ),
        ], $home );

        return $this;
    }


    /**
     * Creates the careers page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addCareers( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Careers',
            'title' => 'Vet Jobs in Hamburg | Alsterpark Vets',
            'path' => 'careers',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Good medicine needs time',
                'subtitle' => 'Careers',
                'text' => 'We plan 20 minutes for every consultation, share the night service fairly and bring our own dogs to work. If that sounds right to you, we would like to meet you.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@alsterpark-vets.example'],
                ],
                'background' => ['id' => $this->img( 'team-work' ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'team-care' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## What you can expect\n\n- A four-day week if you like, with rotas planned two months ahead\n- Pay above the collective agreement and a paid emergency service\n- Five paid training days a year and a budget for congresses\n- Digital X-ray, ultrasound, an in-house lab and a modern operating room\n- Your dog is welcome at work, we have a quiet room for staff pets",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Open positions',
                'columns' => '3',
                'cards' => [
                    ['title' => 'Veterinarian (m/f/d)', 'text' => 'Full or part time, small animal medicine. New graduates get a mentor and a structured first year.'],
                    ['title' => 'Veterinary nurse (m/f/d)', 'text' => 'Full or part time. Consultations, anaesthesia monitoring, lab work and a calm word for every owner.'],
                    ['title' => 'Apprenticeship 2027 (m/f/d)', 'text' => 'Three years of training as a veterinary nurse, with a mentor at your side from day one.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Interested?',
                'text' => 'Send us a few lines about yourself and your CV. A cover letter is not necessary, and we reply within a week.',
                'buttons' => [
                    ['label' => 'Send your application', 'url' => 'mailto:jobs@alsterpark-vets.example'],
                    ['label' => 'Meet the team', 'url' => '/team'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the pet care guides page and its articles below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addGuides( Page $home ) : static
    {
        $guides = $this->guides( $home );

        $this->guide( $guides, [
            'name' => 'Your puppy\'s first year',
            'title' => 'Your Puppy\'s First Year: Vaccinations, Worming and More',
            'path' => 'puppy-guide',
        ], 'A healthy start for your puppy',
            "The first twelve months decide a lot about your dog's health. Vaccinations protect against dangerous infections, regular worming keeps the gut healthy, and good food builds strong bones and joints.\n\nAt Alsterpark Vets, your puppy gets a vaccination plan, a microchip and a pet passport, and you get answers to all the questions that come up in the first weeks: how much to feed, when to start training and when neutering makes sense.",
            'puppy-golden',
            [
                ['title' => '8 weeks', 'text' => 'The right age for the first vaccination'],
                ['title' => '3', 'text' => 'Vaccination visits in the first year'],
                ['title' => '€0', 'text' => 'The first puppy check-up is free'],
            ],
            [
                ['label' => '8 weeks', 'title' => 'First visit', 'text' => 'Health check, first vaccination, worming and plenty of treats.'],
                ['label' => '12 weeks', 'title' => 'Second vaccination', 'text' => 'Booster, microchip and pet passport, plus advice on food.'],
                ['label' => '16 weeks', 'title' => 'Third vaccination', 'text' => 'Including rabies, so your puppy can travel with you.'],
                ['label' => '6 months', 'title' => 'Growth check', 'text' => 'Teeth, weight and joints, and a talk about neutering.'],
                ['label' => '15 months', 'title' => 'First booster', 'text' => 'Completes the basic immunisation for the years ahead.'],
            ],
            [
                ['title' => 'When can my puppy meet other dogs?', 'text' => 'Puppies need contact with other dogs early on. Choose healthy, vaccinated dogs and well-run puppy classes until a week after the third vaccination, and avoid dog parks until then.'],
                ['title' => 'How often should I worm my puppy?', 'text' => 'Every two weeks until twelve weeks of age, then monthly until six months. Afterwards, the interval depends on how your dog lives, and we make a plan with you.'],
                ['title' => 'When should I neuter my dog?', 'text' => 'It depends on breed, size and sex. For many dogs, waiting until they are fully grown is better for the joints. We talk it through at the growth check.'],
            ],
        );

        $this->guide( $guides, [
            'name' => 'Brushing your pet\'s teeth',
            'title' => 'How to Brush Your Dog\'s or Cat\'s Teeth',
            'path' => 'dental-guide',
        ], 'Healthy teeth in two minutes a day',
            "Most dogs and cats over three years old have some form of dental disease. It starts with plaque, turns into tartar and inflamed gums, and can end in painful, loose teeth. Daily brushing is the best way to prevent it.\n\nIt sounds harder than it is. With a soft brush, a pet toothpaste and a few days of patience, most dogs and many cats learn to enjoy the routine, especially when it ends with a reward.",
            'dental-exam',
            [
                ['title' => '80%', 'text' => 'Of dogs over three show signs of dental disease'],
                ['title' => '2 min', 'text' => 'Of brushing a day are enough'],
                ['title' => '1×', 'text' => 'Dental check a year at the vet'],
            ],
            [
                ['label' => 'Days 1–3', 'title' => 'Get used to touch', 'text' => 'Lift the lips gently and touch the teeth with your finger.'],
                ['label' => 'Days 4–6', 'title' => 'Taste the paste', 'text' => 'Let your pet lick pet toothpaste from your finger. Never use human toothpaste.'],
                ['label' => 'Week 2', 'title' => 'First brushing', 'text' => 'Brush the outer surfaces of the back teeth in small circles.'],
                ['label' => 'Week 3', 'title' => 'Daily routine', 'text' => 'Brush all outer surfaces every day, always at the same time.'],
            ],
            [
                ['title' => 'What if my cat won\'t let me brush?', 'text' => 'Many cats need more time. Start with a finger brush and a few seconds a day. Dental diets and chews help too, but they don\'t replace brushing.'],
                ['title' => 'Which signs mean a dental check is due?', 'text' => 'Bad breath, red gums, brown deposits, chewing on one side, dropping food or pawing at the mouth. Please book an appointment if you notice any of these.'],
                ['title' => 'Why does dental cleaning need anaesthesia?', 'text' => 'We clean below the gum line and take dental X-rays, which isn\'t possible in an awake animal. Anaesthesia also prevents stress and pain.'],
            ],
        );

        $this->guide( $guides, [
            'name' => 'Calm trips with your cat',
            'title' => 'How to Take Your Cat to the Vet Without Stress',
            'path' => 'cat-travel-guide',
        ], 'From the sofa to the vet, calmly',
            "For many cats, the trip is the hardest part of a vet visit. The carrier only comes out when something unpleasant happens, the car smells strange and the waiting room is full of dogs.\n\nWith a little preparation, you can change that. Our cat waiting room is separate from the dogs, our exam rooms are quiet, and we let your cat leave the carrier in its own time.",
            'cat-lap',
            [
                ['title' => '1', 'text' => 'Separate waiting room only for cats'],
                ['title' => '20 min', 'text' => 'Planned for every cat consultation'],
                ['title' => '30 min', 'text' => 'Before the trip, spray the carrier with calming pheromones'],
            ],
            [
                ['label' => 'Weeks before', 'title' => 'Carrier at home', 'text' => 'Leave it open in the living room with a blanket and treats inside.'],
                ['label' => 'Day of the visit', 'title' => 'Calm start', 'text' => 'Cover the carrier with a towel and carry it with both hands.'],
                ['label' => 'At the clinic', 'title' => 'Cat waiting room', 'text' => 'Put the carrier on the raised shelf, away from the floor.'],
                ['label' => 'At home', 'title' => 'Back to normal', 'text' => 'In multi-cat homes, let the cats sniff each other slowly.'],
            ],
            [
                ['title' => 'Which carrier is best?', 'text' => 'A sturdy carrier that opens at the top and whose lid can be removed. Your cat can then stay in the lower half during the examination.'],
                ['title' => 'Should my cat eat before the visit?', 'text' => 'A small meal is fine for check-ups and makes treats more interesting. For blood tests or procedures under sedation, we tell you when to stop feeding.'],
                ['title' => 'Can you visit us at home instead?', 'text' => 'For very anxious or older cats, we offer home visits in Hamburg on weekday afternoons. Please call us to book one.'],
            ],
        );

        return $this;
    }


    /**
     * Creates the imprint page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addImprint( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Imprint',
            'title' => 'Imprint | Alsterpark Vets',
            'path' => 'imprint',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Imprint\n\n**Alsterpark Vets**\nDr. Lena Brandt and Dr. Omid Karimi, veterinarians, partnership\nHarvestehuder Weg 42\n20149 Hamburg\nGermany\n\nTelephone: 040 5550 1230\nEmail: hello@alsterpark-vets.example\n\nProfessional title: Tierärztin/Tierarzt, awarded in the Federal Republic of Germany\nCompetent chamber: Tierärztekammer Hamburg\nProfessional regulations: Berufsordnung der Tierärztekammer Hamburg, Gebührenordnung für Tierärztinnen und Tierärzte (GOT)\nSupervisory authority: Behörde für Justiz und Verbraucherschutz Hamburg, Veterinary Office\n\nThis is a demo website for the Paws theme. Alsterpark Vets is a fictional clinic, the people shown are not real veterinarians.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the new clients page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addNewClients( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'New clients',
            'title' => 'New Clients | Alsterpark Vets Hamburg',
            'path' => 'new-clients',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Welcome to Alsterpark Vets',
                'subtitle' => 'New clients',
                'text' => 'What to bring to your first visit, how payment and pet insurance work and how to make the trip easy for your pet.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                ],
                'files' => [['id' => $this->img( 'vet-corgi' ), 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'columns' => '3',
                'cards' => [
                    ['title' => 'Your first visit', 'text' => 'Please bring your pet\'s passport or vaccination record, a list of medicines and previous results if you have them. Dogs on a lead, cats and small pets in a closed carrier.', 'file' => ['id' => $this->img( 'chihuahua' ), 'type' => 'file']],
                    ['title' => 'Payment and insurance', 'text' => 'You pay after the visit by card or cash. Fees follow the German veterinary fee schedule, and you get a written estimate before any larger treatment. We send invoices directly to many pet insurers.', 'file' => ['id' => $this->img( 'lab' ), 'type' => 'file']],
                    ['title' => 'Pet emergencies', 'text' => 'Bleeding, breathing problems, poisoning or a swollen belly? Call us right away. Outside our opening hours, call 040 5550 1299, day and night.', 'url' => '/emergencies', 'file' => ['id' => $this->img( 'team-work' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your first visit, step by step',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Arrive', 'text' => 'Dogs and cats wait in separate rooms, with water bowls and treats.'],
                    ['label' => 'Step 2', 'title' => 'Get to know', 'text' => 'We ask about your pet\'s history, food and habits.'],
                    ['label' => 'Step 3', 'title' => 'Examine', 'text' => 'Nose to tail, on the floor or the table, as your pet prefers.'],
                    ['label' => 'Step 4', 'title' => 'Plan', 'text' => 'Clear next steps and costs, written down for you.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'dog-kiss' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## Getting here\n\nThe clinic is on the ground floor of Harvestehuder Weg 42, a short walk from the Alster.\n\n- **Underground:** U1 to Hallerstraße, five minutes on foot\n- **Bus:** line 19 to Alsterchaussee, two minutes on foot\n- **Car:** four parking spaces for clients in the courtyard\n- **Step-free:** level entrance, wide doors and a scale in the floor for large dogs\n\nPlease cancel appointments you can't keep at least 24 hours in advance, so another pet can take your slot.",
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you accept new clients?', 'text' => 'Yes. New clients usually get an appointment within a few days, and sick pets on the same day.'],
                    ['title' => 'Can you transfer my pet\'s records?', 'text' => 'Of course. Tell us the name of your previous vet when you book, and we ask them to send us the records with your consent.'],
                    ['title' => 'Is pet health insurance worth it?', 'text' => 'Surgery and long treatments can be expensive. Insurance or one of our health plans spreads the costs. We are happy to explain the differences, but we don\'t sell insurance.'],
                    ['title' => 'Can I stay with my pet during the examination?', 'text' => 'Yes, for all consultations and most treatments. Only in the operating room and during X-rays do we ask you to wait outside.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Still a question open?',
                'text' => 'Our reception team is happy to help from Monday to Friday 08:00–19:00 and on Saturday 09:00–14:00.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'Call 040 5550 1230', 'url' => 'tel:+494055501230'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the privacy policy page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addPrivacy( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Privacy',
            'title' => 'Privacy Policy | Alsterpark Vets',
            'path' => 'privacy',
            'type' => 'page',
            'status' => 2,
        ], [
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "# Privacy policy\n\n## Who is responsible\n\nAlsterpark Vets, Dr. Lena Brandt and Dr. Omid Karimi, Harvestehuder Weg 42, 20149 Hamburg, hello@alsterpark-vets.example.\n\n## Appointment requests\n\nWhen you send the appointment form, we use your name, phone number, email address and the details about your pet only to plan and confirm the appointment (Art. 6 (1) (b) GDPR). Requests that don't lead to a visit are deleted after six months.\n\n## Client and patient records\n\nWe keep your contact details and your pet's medical records for as long as we treat your pet, and afterwards as long as required by law. They are stored in our clinic software in Germany and are only accessible to our team. We share data with laboratories, referral clinics or your pet insurer only as far as necessary and with your consent.\n\n## This website\n\nThe website doesn't use tracking or advertising cookies. Our server stores technical access data such as the IP address for seven days to protect against attacks. The map is loaded from OpenStreetMap only after you open it.\n\n## Your rights\n\nYou have the right to access, rectification, erasure, restriction of processing and data portability, and you can lodge a complaint with the Hamburg data protection authority.\n\nThis is a demo website for the Paws theme. Alsterpark Vets is a fictional clinic.",
            ]],
        ], $home );

        return $this;
    }


    /**
     * Creates the services page and the service pages below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addServices( Page $home ) : static
    {
        $services = $this->page( [
            'lang' => 'en',
            'name' => 'Services',
            'title' => 'Vet Services in Hamburg | Alsterpark Vets',
            'path' => 'services',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Care for every stage of life',
                'subtitle' => 'Our services',
                'text' => 'From the first puppy vaccination to gentle care for senior pets: modern veterinary medicine with an in-house lab, digital X-ray and enough time for your questions.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                ],
                'files' => [['id' => $this->img( 'puppy-hands' ), 'type' => 'file']],
            ]],
            $this->services(),
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Which animals do you treat?', 'text' => 'Dogs, cats, rabbits, guinea pigs, rats, hamsters and ferrets. For birds, reptiles and horses, we gladly recommend specialised colleagues in Hamburg.'],
                    ['title' => 'How much does a visit cost?', 'text' => 'Fees follow the German veterinary fee schedule (GOT). A general examination with a vaccination costs about €70 to €100 for a dog. You get a written estimate before any larger treatment.'],
                    ['title' => 'Do you refer to specialists?', 'text' => 'Yes. For MRI, orthopaedic surgery or cardiology, we work closely with referral clinics in Hamburg and send them all results, so you don\'t start from scratch.'],
                    ['title' => 'Do you offer home visits?', 'text' => 'Yes, on weekday afternoons in Hamburg, for anxious and older pets and for saying goodbye in familiar surroundings.'],
                ],
            ]],
        ], $home );

        foreach( $this->offers() as $offer ) {
            $this->service( $services, ...$offer );
        }

        return $this;
    }


    /**
     * Creates the team page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addTeam( Page $home ) : static
    {
        $this->page( [
            'lang' => 'en',
            'name' => 'Team',
            'title' => 'Our Vets and Nurses | Alsterpark Vets Hamburg',
            'path' => 'team',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Pet people, through and through',
                'subtitle' => 'Our team',
                'text' => 'Five vets, eight veterinary nurses and four office dogs who know your pet by name.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                ],
                'background' => ['id' => $this->img( 'team-care' ), 'type' => 'file'],
            ]],
            $this->team(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'dog-treat' ), 'type' => 'file'],
                'position' => 'grid-start',
                'ratio' => '1-1',
                'text' => "## A clinic built around animals\n\nDr. Lena Brandt opened the clinic in 2001 in a former bakery near the Alster. In 2015, Dr. Omid Karimi joined as a partner, and the clinic moved to its current rooms with three consulting rooms, an operating room, an in-house lab and separate waiting rooms for dogs and cats.\n\nWe plan 20 minutes for every consultation, handle animals with as little restraint as possible and explain every step and every cost before we start.",
            ]],
            ['id' => Utils::uid(), 'type' => 'slideshow', 'group' => 'main', 'data' => [
                'title' => 'Our clinic',
                'files' => array_map( fn( $key ) => ['id' => $this->cropped( $key, 1500, 1000 ), 'type' => 'file'], ['chihuahua', 'surgery', 'lab', 'team-work'] ),
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Qualified and certified',
                'layout' => 'badges',
                'cards' => [
                    ['title' => 'Veterinary chamber', 'text' => 'Members of the Hamburg veterinary chamber', 'file' => $this->icon( 'award' )],
                    ['title' => 'Cat friendly', 'text' => 'Separate cat waiting room and exam room', 'file' => $this->icon( 'heart' )],
                    ['title' => 'Low-stress handling', 'text' => 'The whole team is trained in gentle handling', 'file' => $this->icon( 'paw' )],
                    ['title' => 'Dentistry', 'text' => 'Further training in veterinary dentistry', 'file' => $this->icon( 'award' )],
                    ['title' => 'Hygiene', 'text' => 'Validated sterilisation, inspected every year', 'file' => $this->icon( 'shield' )],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What pet owners say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Join our team',
                'text' => 'We are looking for vets, veterinary nurses and apprentices who share our calm way of working.',
                'buttons' => [
                    ['label' => 'Open positions', 'url' => '/careers'],
                ],
            ]],
        ], $home );

        return $this;
    }


    /**
     * Returns the clinic highlights element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function badges() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Why pets like it here',
            'layout' => 'badges',
            'cards' => [
                ['title' => 'Separate cat room', 'text' => 'A quiet waiting room without dogs', 'file' => $this->icon( 'heart' )],
                ['title' => 'Low-stress handling', 'text' => 'Treats, patience and as little restraint as possible', 'file' => $this->icon( 'paw' )],
                ['title' => 'Same-day slots', 'text' => 'For sick and injured pets every day', 'file' => $this->icon( 'clock' )],
                ['title' => 'Day and night', 'text' => 'Emergency number outside opening hours', 'file' => $this->icon( 'phone' )],
                ['title' => 'Home visits', 'text' => 'For anxious and older pets in Hamburg', 'file' => $this->icon( 'home' )],
            ],
        ]];
    }


    /**
     * Creates the shared Alsterpark footer and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'cards', 'Alsterpark footer', ['columns' => '4', 'cards' => [
            ['title' => 'Opening hours', 'text' => "- Mon–Fri: 08:00–19:00\n- Sat: 09:00–14:00\n- Emergencies: [040 5550 1299](tel:+494055501299)"],
            ['title' => 'Services', 'text' => "- [Check-ups and vaccinations](/vaccinations)\n- [Puppy and kitten care](/puppy-kitten-care)\n- [Dental care](/dental-care)\n- [Cat-friendly care](/cat-care)\n- [Senior pets](/senior-pets)\n- [Pet emergencies](/emergencies)"],
            ['title' => 'Clinic', 'text' => "- [Our team](/team)\n- [New clients](/new-clients)\n- [Pet care guides](/guides)\n- [Careers](/careers)\n- [Imprint](/imprint)\n- [Privacy](/privacy)"],
            ['title' => 'Contact', 'text' => "Harvestehuder Weg 42\n20149 Hamburg\n\n040 5550 1230\n[Book a visit](/appointment)"],
        ]] );
    }


    /**
     * Returns the ID of the primary clinic image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'dog-smile' );
    }


    /**
     * Creates a pet care guide below the guides page.
     *
     * @param Page $parent Guides page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Article headline
     * @param string $text Article text
     * @param string $cover PHOTOS key of the cover image
     * @param array<int, array<string, string>> $facts Key facts as figure cards
     * @param array<int, array<string, string>> $steps Care steps
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function guide( Page $parent, array $data, string $title, string $text, string $cover,
        array $facts, array $steps, array $questions ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article( $title, $text, $this->img( $cover ) ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'columns' => '3',
                'cards' => $facts,
            ]],
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Step by step',
                'layout' => 'vertical',
                'items' => $steps,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from pet owners',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Any questions left?',
                'text' => 'We are happy to talk them through at your next visit. Book an appointment online or call us.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'Call 040 5550 1230', 'url' => 'tel:+494055501230'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Creates the pet care guides overview page and returns it.
     *
     * @param Page $home Home page
     * @return Page Guides page
     */
    protected function guides( Page $home ) : Page
    {
        return $this->page( [
            'id' => $this->guidesId,
            'lang' => 'en',
            'name' => 'Pet care guides',
            'title' => 'Pet Care Guides | Alsterpark Vets',
            'path' => 'guides',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Good care starts at home',
                'subtitle' => 'Pet care guides',
                'text' => 'Practical advice from our vets for the everyday questions of pet owners, from the first puppy weeks to calm trips with your cat.',
            ]],
            ['id' => 'guide-list', 'type' => 'blog', 'group' => 'main', 'data' => [
                'layout' => 'cards',
                'parent-page' => ['value' => $this->guidesId, 'label' => 'Pet care guides'],
                'order' => '_lft',
                'limit' => 12,
            ]],
        ], $home );
    }


    /**
     * Returns the Alsterpark home page.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Alsterpark Vets'], 'config' ),
        ] + $this->logos( $this->logoFile() ) + [
            'paws::clinic' => [
                'type' => 'paws::clinic',
                'files' => [],
                'data' => [
                    'name' => 'Alsterpark Vets',
                    'business-type' => 'VeterinaryCare',
                    'street-address' => 'Harvestehuder Weg 42',
                    'postal-code' => '20149',
                    'locality' => 'Hamburg',
                    'country' => 'DE',
                    'telephone' => '+49 40 5550 1230',
                    'email' => 'hello@alsterpark-vets.example',
                    'emergency-phone' => '+49 40 5550 1299',
                    'booking' => '/appointment',
                    'languages' => 'English, German, Persian, Portuguese',
                    'species' => 'Dogs, Cats, Rabbits, Guinea pigs, Ferrets',
                    'new-clients' => true,
                    'price-range' => '€€',
                    'call-button' => true,
                    'hours' => [
                        ['id' => 'mon', 'day' => 'Monday', 'opens' => '08:00', 'closes' => '19:00'],
                        ['id' => 'tue', 'day' => 'Tuesday', 'opens' => '08:00', 'closes' => '19:00'],
                        ['id' => 'wed', 'day' => 'Wednesday', 'opens' => '08:00', 'closes' => '19:00'],
                        ['id' => 'thu', 'day' => 'Thursday', 'opens' => '08:00', 'closes' => '19:00'],
                        ['id' => 'fri', 'day' => 'Friday', 'opens' => '08:00', 'closes' => '19:00'],
                        ['id' => 'sat', 'day' => 'Saturday', 'opens' => '09:00', 'closes' => '14:00'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Kind vet care for the whole family',
                'subtitle' => 'Your vets in Hamburg',
                'text' => "Check-ups, surgery and emergencies for dogs, cats and small pets, with a separate cat room, same-day slots and vets who take their time.\n\n★★★★★ Rated 4.9 out of 5 by 680 pet owners",
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'Our services', 'url' => '/services'],
                ],
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'figures',
                'cards' => [
                    ['title' => '25 years', 'text' => 'Caring for pets in Hamburg'],
                    ['title' => '5 vets', 'text' => 'And eight veterinary nurses'],
                    ['title' => 'Same day', 'text' => 'Appointments for sick pets'],
                    ['title' => '4.9/5', 'text' => 'From 680 pet owner reviews'],
                ],
            ]],
            $this->services(),
            $this->badges(),
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'cat-exam' ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => "## Less stress, better care\n\nA calm pet is easier to examine, and an easier examination means a better diagnosis. That's why cats wait in their own room, dogs get treats instead of being held down, and small pets are weighed in their carrier.\n\nWe plan 20 minutes for every consultation, examine on the floor if your dog prefers it and explain every step and every cost before we start.",
            ]],
            $this->plans(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Your first visit',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => 'Step 1', 'title' => 'Book', 'text' => 'Online or by phone, sick pets on the same day.'],
                    ['label' => 'Step 2', 'title' => 'Arrive', 'text' => 'Separate waiting rooms for cats and dogs.'],
                    ['label' => 'Step 3', 'title' => 'Examine', 'text' => 'Nose to tail, with treats and patience.'],
                    ['label' => 'Step 4', 'title' => 'Plan', 'text' => 'Clear next steps and costs, no pressure.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'What pet owners say',
                'items' => $this->reviews(),
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Pet care guides',
                'layout' => 'cards',
                'parent-page' => ['value' => $this->guidesId, 'label' => 'Pet care guides'],
                'order' => '_lft',
                'limit' => 3,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Common questions',
                'items' => [
                    ['title' => 'Do you accept new clients?', 'text' => 'Yes. New clients usually get an appointment within a few days, and sick pets on the same day.'],
                    ['title' => 'Which animals do you treat?', 'text' => 'Dogs, cats, rabbits, guinea pigs and other small mammals. For birds and reptiles, we recommend specialised colleagues.'],
                    ['title' => 'What does a visit cost?', 'text' => 'Fees follow the German veterinary fee schedule. A check-up with a vaccination costs about €70 to €100, and you get a written estimate before any larger treatment.'],
                    ['title' => 'What if my pet gets sick at night?', 'text' => 'Call our emergency number 040 5550 1299. It connects you to the vet on duty, day and night.'],
                ],
            ]],
            $this->map( 'Visit us' ),
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Time for a check-up?',
                'text' => 'Book your visit online in two minutes. New clients are welcome, and sick pets are seen on the same day.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'Call 040 5550 1230', 'url' => 'tel:+494055501230'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Alsterpark Vets in Hamburg: check-ups, vaccinations, dental care, surgery and emergencies for dogs, cats and small pets, with a separate cat room.',
                'keywords' => 'vet Hamburg, veterinary clinic, animal hospital, dog vet, cat vet, rabbit vet, pet vaccinations, pet emergency',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Alsterpark Vets | Your Vets in Hamburg',
                'description' => 'Kind vet care for dogs, cats and small pets, with vets who take their time.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Alsterpark Vets | Your Vets in Hamburg', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a coral line icon once and returns its file reference.
     *
     * @param string $name Icon name: award, clock, heart, home, paw, phone or shield
     * @return array<string, string> File reference
     */
    protected function icon( string $name ) : array
    {
        $paths = [
            'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'heart' => '<path d="M12 20s-7.5-4.6-7.5-10A4.3 4.3 0 0 1 12 7.4 4.3 4.3 0 0 1 19.5 10c0 5.4-7.5 10-7.5 10z"/>',
            'home' => '<path d="M4 11 12 4l8 7"/><path d="M6 10v10h12V10"/><path d="M10 20v-5h4v5"/>',
            'paw' => '<path d="M12 12c-2.5 0-5 2.8-5 5.2 0 1.5 1 2.3 2.3 2.3 1.1 0 1.7-.6 2.7-.6s1.6.6 2.7.6c1.3 0 2.3-.8 2.3-2.3 0-2.4-2.5-5.2-5-5.2z"/><circle cx="6" cy="10" r="1.6"/><circle cx="9.5" cy="6" r="1.7"/><circle cx="14.5" cy="6" r="1.7"/><circle cx="18" cy="10" r="1.6"/>',
            'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
            'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        ];

        $this->icons[$name] ??= $this->svgFile(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#C2412B" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[$name] . '</svg>',
            'icon-' . $name . '.svg',
            ucfirst( $name ) . ' icon',
            'Coral line icon: ' . $name,
            true,
        );

        return ['id' => $this->icons[$name], 'type' => 'file'];
    }


    /**
     * Creates the Alsterpark SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 80" role="img" aria-labelledby="title desc">
  <title id="title">Alsterpark Vets logo</title>
  <desc id="desc">Coral circle with a white paw print beside the Alsterpark Vets wordmark</desc>
  <circle cx="38" cy="40" r="32" fill="#C2412B"/>
  <path d="M38 38c-5.5 0-11 6-11 11.2 0 3.2 2.3 5 5.2 5 2.4 0 3.7-1.3 5.8-1.3s3.4 1.3 5.8 1.3c2.9 0 5.2-1.8 5.2-5 0-5.2-5.5-11.2-11-11.2z" fill="#FFFFFF"/>
  <ellipse cx="24.5" cy="34" rx="3.6" ry="4.6" transform="rotate(-20 24.5 34)" fill="#FFFFFF"/>
  <ellipse cx="32.5" cy="26" rx="3.8" ry="5" fill="#FFFFFF"/>
  <ellipse cx="43.5" cy="26" rx="3.8" ry="5" fill="#FFFFFF"/>
  <ellipse cx="51.5" cy="34" rx="3.6" ry="4.6" transform="rotate(20 51.5 34)" fill="#FFFFFF"/>
  <text x="84" y="52" fill="#16324F" font-family="'Iowan Old Style', 'Palatino Linotype', Palatino, Georgia, serif" font-size="34" font-weight="600">Alsterpark <tspan fill="#C2412B" font-style="italic" font-weight="400">Vets</tspan></text>
</svg>
SVG;

            $this->logoFile = $this->svgFile(
                $svg,
                'alsterpark-logo.svg',
                'Alsterpark Vets logo',
                'Coral circle with a white paw print beside the Alsterpark Vets wordmark',
                true,
            );
        }

        return $this->logoFile;
    }


    /**
     * Returns the map element with address, opening hours and directions.
     *
     * @param string $title Map headline
     * @return array<string, mixed> Map content element
     */
    protected function map( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'map', 'group' => 'main', 'data' => [
            'title' => $title,
            'text' => "**Alsterpark Vets**\nHarvestehuder Weg 42 · 20149 Hamburg\n\n**Opening hours**\nMonday to Friday 08:00–19:00\nSaturday 09:00–14:00\n\n**Call**\n040 5550 1230\n\n**Pet emergencies, day and night**\n040 5550 1299\n\n**Getting here**\nU1 to Hallerstraße or bus 19 to Alsterchaussee, client parking in the courtyard.",
            'location' => [
                'latitude' => 53.5768,
                'longitude' => 9.9967,
                'zoom' => 16,
            ],
            'button' => 'Open in OpenStreetMap',
        ]];
    }


    /**
     * Returns the arguments for the service pages.
     *
     * @return array<int, array<int, mixed>> Page data, headline, hero and image keys, text, included items and questions
     */
    protected function offers() : array
    {
        return [
            [['name' => 'Check-ups and vaccinations', 'title' => 'Pet Check-ups and Vaccinations in Hamburg | Alsterpark Vets', 'path' => 'vaccinations'],
                'Prevention keeps tails wagging', 'dog-injection', 'dog-hug',
                "## A yearly check-up, nose to tail\n\nPets hide pain and illness well. At the yearly check-up, we look at eyes, ears, teeth, heart, lungs, skin, joints and weight, and often find problems long before they become serious.\n\nVaccinations follow the current recommendations of the German standing committee on veterinary vaccination. We only vaccinate what your pet really needs, depending on its age, lifestyle and travel plans.\n\nCheck-ups are done by [all our vets](/team). Read [your puppy's first year](/puppy-guide) for the vaccination plan of young dogs.",
                [
                    ['title' => 'Health check', 'text' => 'Eyes, ears, teeth, heart, lungs, skin, joints and weight.'],
                    ['title' => 'Vaccinations', 'text' => 'Core and lifestyle vaccines, tailored to your pet.'],
                    ['title' => 'Parasite plan', 'text' => 'Worms, fleas and ticks, with as little treatment as necessary.'],
                ],
                [
                    ['title' => 'How often should my pet be vaccinated?', 'text' => 'After the basic immunisation, many core vaccines only need a booster every three years. Leptospirosis for dogs and some cat vaccines are given yearly.'],
                    ['title' => 'What does a check-up cost?', 'text' => 'A general examination with a vaccination costs about €70 to €100 for a dog and slightly less for a cat. It is included in our health plans.'],
                ]],
            [['name' => 'Puppy and kitten care', 'title' => 'Puppy and Kitten Care in Hamburg | Alsterpark Vets', 'path' => 'puppy-kitten-care'],
                'A healthy start in life', 'puppy-golden', 'kitten-hands',
                "## Good first experiences\n\nThe first visits shape how your pet feels about the vet for the rest of its life. That's why we take extra time, play, give treats and keep the examinations short and gentle.\n\nYour puppy or kitten gets its vaccinations, worming, a microchip and a pet passport. You get advice on food, training, socialisation and the right time for neutering, with a written plan for the first year.\n\nOur youngest patients are looked after by [Dr. Priya Nair](/team). Read [your puppy's first year](/puppy-guide) for every step.",
                [
                    ['title' => 'Vaccination plan', 'text' => 'All vaccinations of the first year, at the right time.'],
                    ['title' => 'Microchip and passport', 'text' => 'Registered and ready for travel within the EU.'],
                    ['title' => 'Happy visits', 'text' => 'Short visits with treats only, so the clinic smells of good things.'],
                ],
                [
                    ['title' => 'When should my puppy or kitten first see a vet?', 'text' => 'Within the first week after you bring it home, usually at eight to nine weeks of age.'],
                    ['title' => 'Is the first visit really free?', 'text' => 'Yes. The first check-up of a new puppy or kitten is free, vaccinations and medicines are charged according to the fee schedule.'],
                ]],
            [['name' => 'Dental care', 'title' => 'Dental Care for Dogs and Cats in Hamburg | Alsterpark Vets', 'path' => 'dental-care'],
                'Healthy teeth, happy pets', 'dental-exam', 'dachshund-exam',
                "## More than fresh breath\n\nDental disease is one of the most common problems in dogs and cats, and one of the most painful. Many pets keep eating anyway, so owners often notice only bad breath or red gums.\n\nAt a dental check, we look at teeth and gums in the awake animal. For cleaning, we use anaesthesia with full monitoring, take dental X-rays, scale and polish every tooth and remove teeth that can't be saved.\n\nDental treatments are done by [Dr. Omid Karimi](/team), who has completed further training in veterinary dentistry. Read [how to brush your pet's teeth](/dental-guide) at home.",
                [
                    ['title' => 'Dental check', 'text' => 'Teeth and gums examined during any visit.'],
                    ['title' => 'Cleaning under anaesthesia', 'text' => 'Scaling and polishing above and below the gum line.'],
                    ['title' => 'Dental X-ray', 'text' => 'Shows the roots, where most problems hide.'],
                ],
                [
                    ['title' => 'What does a dental cleaning cost?', 'text' => 'A cleaning under anaesthesia with dental X-rays costs about €350 to €600, depending on the size of your pet and the extractions needed. You get a written estimate first.'],
                    ['title' => 'Is anaesthesia safe for my older pet?', 'text' => 'We run a blood test first, monitor heart, breathing and blood pressure throughout and keep your pet warm. Age alone is not a reason against a necessary treatment.'],
                ]],
            [['name' => 'Surgery', 'title' => 'Pet Surgery and Neutering in Hamburg | Alsterpark Vets', 'path' => 'surgery'],
                'Safe surgery, gentle recovery', 'surgery', 'team-work',
                "## Planned with care\n\nOur operating room is equipped for soft tissue surgery, neutering, tumour removal and wound care. Every anaesthesia is monitored by a trained veterinary nurse with ECG, pulse oximetry, capnography and blood pressure.\n\nPain management starts before the operation and continues at home. You get clear written instructions, a check-up appointment and a phone number to call if you are worried.\n\nSurgery is performed by [Dr. Lena Brandt](/team) and [Dr. Omid Karimi](/team). Orthopaedic cases are referred to specialists in Hamburg.",
                [
                    ['title' => 'Neutering', 'text' => 'For dogs, cats and rabbits, usually as a day patient.'],
                    ['title' => 'Soft tissue surgery', 'text' => 'Lumps, wounds, foreign bodies and more.'],
                    ['title' => 'Pain management', 'text' => 'Before, during and after the operation.'],
                ],
                [
                    ['title' => 'Can my pet go home on the same day?', 'text' => 'After most routine operations, yes. You collect your pet in the afternoon, once it is awake, warm and comfortable.'],
                    ['title' => 'What does neutering cost?', 'text' => 'Castration of a male cat costs about €120 to €180, spaying a female dog about €450 to €750 depending on size. The estimate includes anaesthesia and pain relief.'],
                ]],
            [['name' => 'Diagnostics', 'title' => 'Lab, X-Ray and Ultrasound for Pets in Hamburg | Alsterpark Vets', 'path' => 'diagnostics'],
                'Answers, often while you wait', 'lab', 'chihuahua',
                "## In-house lab and imaging\n\nWaiting for results is hard. With our in-house lab, most blood and urine tests are ready within half an hour, so we can start the right treatment during the same visit.\n\nDigital X-ray and ultrasound show bones, lungs, heart and abdominal organs without delay. If a specialist opinion is needed, we send the images to radiologists the same day.\n\nUltrasound examinations are done by [Dr. Priya Nair](/team).",
                [
                    ['title' => 'Blood and urine tests', 'text' => 'Most results within 30 minutes.'],
                    ['title' => 'Digital X-ray', 'text' => 'Low dose, sharp images and fast results.'],
                    ['title' => 'Ultrasound', 'text' => 'Abdomen and heart, often without sedation.'],
                ],
                [
                    ['title' => 'Does my pet need to fast before a blood test?', 'text' => 'For most tests, a fast of eight to twelve hours gives the most reliable results. Water is always allowed.'],
                    ['title' => 'Can I stay during the X-ray?', 'text' => 'For safety reasons, only our team stays in the X-ray room. Most X-rays take only a few minutes, and you can be back with your pet right away.'],
                ]],
            [['name' => 'Cat-friendly care', 'title' => 'Cat-Friendly Vet in Hamburg | Alsterpark Vets', 'path' => 'cat-care'],
                'Cats are not small dogs', 'cat-exam', 'cat-stroke',
                "## Designed for cats\n\nCats wait in their own quiet room, away from barking and dog smells. The carriers sit on raised shelves, and calming pheromones fill the exam room.\n\nWe let your cat come out of the carrier in its own time, examine it where it feels safe and use towels instead of force. For anxious cats, we offer medication before the visit and home visits.\n\nOur cat patients are looked after by [Dr. Lena Brandt](/team). Read [how to take your cat to the vet without stress](/cat-travel-guide).",
                [
                    ['title' => 'Cat waiting room', 'text' => 'Separate, quiet and without dogs.'],
                    ['title' => 'Gentle handling', 'text' => 'Towels, patience and treats instead of restraint.'],
                    ['title' => 'Home visits', 'text' => 'For very anxious or older cats in Hamburg.'],
                ],
                [
                    ['title' => 'My cat only lives indoors. Does it need check-ups?', 'text' => 'Yes. Indoor cats get kidney disease, dental problems and overweight as well, and a yearly check-up finds them early.'],
                    ['title' => 'Can my cat get something to calm down before the visit?', 'text' => 'Yes. A mild medication given at home two hours before the visit helps many cats. Ask us at your next appointment.'],
                ]],
            [['name' => 'Rabbits and small pets', 'title' => 'Vet for Rabbits and Small Pets in Hamburg | Alsterpark Vets', 'path' => 'small-pets'],
                'Small pets, full attention', 'rabbit', 'rabbit-window',
                "## Know-how for small mammals\n\nRabbits, guinea pigs and other small pets are prey animals. They hide illness until it is serious, so regular check-ups and quick action matter even more.\n\nWe check teeth, which grow throughout their lives, weight, skin and digestion, vaccinate rabbits against myxomatosis and RHD and give advice on housing, company and the right diet.\n\nSmall pets are looked after by [Dr. Priya Nair](/team).",
                [
                    ['title' => 'Dental checks', 'text' => 'Front and back teeth, with trimming if needed.'],
                    ['title' => 'Rabbit vaccinations', 'text' => 'Against myxomatosis and both RHD strains.'],
                    ['title' => 'Neutering', 'text' => 'With anaesthesia adapted to small mammals.'],
                ],
                [
                    ['title' => 'My rabbit has stopped eating. What should I do?', 'text' => 'Call us right away. A rabbit that doesn\'t eat for more than twelve hours is an emergency, because its gut can stop working.'],
                    ['title' => 'How often should small pets see a vet?', 'text' => 'Once a year for a check-up, rabbits also for their vaccinations. Older animals every six months.'],
                ]],
            [['name' => 'Senior pets', 'title' => 'Senior Pet Care in Hamburg | Alsterpark Vets', 'path' => 'senior-pets'],
                'Golden years, well cared for', 'dachshund-exam', 'golden-kiss',
                "## Comfort in later life\n\nFrom about eight years for dogs and ten for cats, the body changes. Kidneys, heart, joints and teeth need more attention, and many problems can be managed well when found early.\n\nOur senior check-up includes a blood and urine test, blood pressure and a careful look at joints and mobility. For arthritis and chronic pain, we combine medication, weight management, physiotherapy exercises and small changes at home.\n\nWhen the time comes to say goodbye, we take our time, at the clinic or at your home.",
                [
                    ['title' => 'Senior check-up', 'text' => 'Blood and urine tests and blood pressure twice a year.'],
                    ['title' => 'Pain management', 'text' => 'Modern treatment for arthritis and chronic pain.'],
                    ['title' => 'Saying goodbye', 'text' => 'Calm and dignified, also at home.'],
                ],
                [
                    ['title' => 'How do I know if my pet is in pain?', 'text' => 'Older pets rarely cry. Watch for stiffness after rest, reluctance to jump or climb stairs, less grooming, hiding or a changed mood.'],
                    ['title' => 'How often should senior pets have a check-up?', 'text' => 'Every six months. Six months in a pet\'s life is like two to three years in ours.'],
                ]],
            [['name' => 'Pet emergencies', 'title' => 'Pet Emergencies in Hamburg | Alsterpark Vets', 'path' => 'emergencies'],
                'Your pet needs help? Call us now', 'team-work', 'vet-corgi',
                "## Help on the same day\n\nDuring opening hours, we see sick and injured pets on the same day, including new clients. Please call ahead, so we can prepare and treat urgent cases first.\n\nOutside our opening hours, call our emergency number **040 5550 1299**. It connects you to the vet on duty, day and night.\n\n## Go now if your pet\n\n- has trouble breathing or blue gums\n- is bleeding heavily or was hit by a car\n- ate something poisonous, such as chocolate, grapes, rat poison or medicines\n- has a swollen, hard belly and retches without vomiting\n- can't pass urine, especially male cats",
                [
                    ['title' => 'Same-day slots', 'text' => 'Kept free for sick and injured pets every day.'],
                    ['title' => 'Day and night', 'text' => 'Emergency number outside opening hours.'],
                    ['title' => 'Intensive care', 'text' => 'Oxygen, infusions and monitoring at the clinic.'],
                ],
                [
                    ['title' => 'Do you treat emergencies for new clients?', 'text' => 'Yes. Bring your pet\'s passport if you have it at hand, we take care of the paperwork after the treatment.'],
                    ['title' => 'Why are emergency fees higher?', 'text' => 'The German veterinary fee schedule requires higher fees at night, at weekends and on public holidays, together with a fixed emergency service fee.'],
                ]],
        ];
    }


    /**
     * Creates a Paws demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->ids( $content )[0] ?? $this->file();

        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data, $content, $parent, $elementId, $fileId, $footer, 'Alsterpark Vets, vet Hamburg, veterinary clinic, dog vet, cat vet, pet vaccinations, pet emergency' );
    }


    /**
     * Builds the Paws demo page tree.
     */
    protected function pages() : void
    {
        $this->guidesId = (string) Str::uuid7();
        $home = $this->home();

        $this->addServices( $home )
            ->addTeam( $home )
            ->addNewClients( $home )
            ->addGuides( $home )
            ->addAppointment( $home )
            ->addCareers( $home )
            ->addImprint( $home )
            ->addPrivacy( $home );
    }


    /**
     * Returns the health plans element.
     *
     * @return array<string, mixed> Pricing content element
     */
    protected function plans() : array
    {
        return ['id' => Utils::uid(), 'type' => 'pricing', 'group' => 'main', 'data' => [
            'title' => 'Health plans',
            'text' => 'Spread the cost of routine care over the year. Pay-as-you-go is always possible, and you can cancel any plan monthly.',
            'items' => [
                [
                    'name' => 'Puppy and kitten',
                    'prices' => [['id' => 'young', 'amount' => 19, 'label' => '€19 / month']],
                    'text' => 'For the first year of your new family member.',
                    'features' => "- All first-year vaccinations\n- Microchip and pet passport\n- Worming and flea treatment\n- Neutering discount of 15%",
                    'url' => '/puppy-kitten-care',
                    'button' => 'Puppy and kitten care',
                ],
                [
                    'name' => 'Adult',
                    'prices' => [['id' => 'adult', 'amount' => 16, 'label' => '€16 / month']],
                    'text' => 'Yearly prevention for healthy dogs and cats.',
                    'features' => "- Yearly check-up and vaccinations\n- Six-monthly weight and dental check\n- Parasite prevention\n- 10% off all other treatments",
                    'url' => '/vaccinations',
                    'button' => 'Check-ups and vaccinations',
                    'highlight' => true,
                    'badge' => 'Most chosen',
                ],
                [
                    'name' => 'Senior',
                    'prices' => [['id' => 'senior', 'amount' => 29, 'label' => '€29 / month']],
                    'text' => 'For dogs from eight and cats from ten years.',
                    'features' => "- Two senior check-ups a year\n- Blood and urine tests\n- Blood pressure measurement\n- 10% off all other treatments",
                    'url' => '/senior-pets',
                    'button' => 'Senior pet care',
                ],
            ],
        ]];
    }


    /**
     * Returns the pet owner reviews.
     *
     * @return array<int, array<string, string>> Testimonial items
     */
    protected function reviews() : array
    {
        return [
            ['name' => 'Julia R. with Mila', 'role' => 'Cat owner since 2017', 'text' => 'Mila used to hide for hours after every vet visit. Here she waits in a quiet room and comes out of the carrier on her own. Last time, she even purred on the table.'],
            ['name' => 'Thomas and Bea K.', 'role' => 'Owners of Bruno, 13', 'text' => 'Since his arthritis treatment, our old Labrador climbs the stairs again. The vets explained every option and never pushed us towards anything.'],
            ['name' => 'Selin A.', 'role' => 'Rabbit owner', 'text' => 'On a Saturday, our rabbit stopped eating. We got an appointment within the hour, and Dr. Nair called us in the evening to ask how he was doing.'],
        ];
    }


    /**
     * Creates a service page below the services page.
     *
     * @param Page $parent Services page
     * @param array<string, string> $data Page name, title and path
     * @param string $title Hero headline
     * @param string $hero PHOTOS key of the hero image
     * @param string $image PHOTOS key of the text image
     * @param string $text Service description
     * @param array<int, array<string, string>> $items What is included
     * @param array<int, array<string, string>> $questions Frequently asked questions
     * @return Page Created page
     */
    protected function service( Page $parent, array $data, string $title, string $hero, string $image,
        string $text, array $items, array $questions ) : Page
    {
        return $this->page( $data + [
            'lang' => 'en',
            'type' => 'page',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => $title,
                'subtitle' => $data['name'],
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'All services', 'url' => '/services'],
                ],
                'background' => ['id' => $this->img( $hero ), 'type' => 'file'],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( $image ), 'type' => 'file'],
                'position' => 'grid-end',
                'ratio' => '1-1',
                'text' => $text,
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'What we offer',
                'cards' => $items,
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Questions from pet owners',
                'items' => $questions,
            ]],
            ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
                'title' => 'Let\'s look after your pet together',
                'text' => 'Book a visit online or call us. New clients usually get an appointment within a few days, sick pets on the same day.',
                'buttons' => [
                    ['label' => 'Book a visit', 'url' => '/appointment'],
                    ['label' => 'Call 040 5550 1230', 'url' => 'tel:+494055501230'],
                ],
            ]],
        ], $parent );
    }


    /**
     * Returns the services card element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function services() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'How we care for your pet',
            'columns' => '3',
            'cards' => [
                ['title' => 'Check-ups and vaccinations', 'text' => 'A yearly nose-to-tail check and only the vaccines your pet needs.', 'url' => '/vaccinations', 'file' => ['id' => $this->img( 'dog-injection' ), 'type' => 'file']],
                ['title' => 'Puppy and kitten care', 'text' => 'Vaccinations, microchip and a good start with happy first visits.', 'url' => '/puppy-kitten-care', 'file' => ['id' => $this->img( 'puppy-hands' ), 'type' => 'file']],
                ['title' => 'Dental care', 'text' => 'Dental checks, X-rays and gentle cleaning under anaesthesia.', 'url' => '/dental-care', 'file' => ['id' => $this->img( 'dental-exam' ), 'type' => 'file']],
                ['title' => 'Surgery', 'text' => 'Neutering and soft tissue surgery with careful monitoring.', 'url' => '/surgery', 'file' => ['id' => $this->img( 'surgery' ), 'type' => 'file']],
                ['title' => 'Diagnostics', 'text' => 'In-house lab, digital X-ray and ultrasound, often while you wait.', 'url' => '/diagnostics', 'file' => ['id' => $this->img( 'lab' ), 'type' => 'file']],
                ['title' => 'Cat-friendly care', 'text' => 'A separate cat room and patient, gentle handling.', 'url' => '/cat-care', 'file' => ['id' => $this->img( 'cat-exam' ), 'type' => 'file']],
                ['title' => 'Rabbits and small pets', 'text' => 'Teeth, vaccinations and advice for rabbits and guinea pigs.', 'url' => '/small-pets', 'file' => ['id' => $this->img( 'rabbit' ), 'type' => 'file']],
                ['title' => 'Senior pets', 'text' => 'Check-ups and pain management for comfortable later years.', 'url' => '/senior-pets', 'file' => ['id' => $this->img( 'dachshund-exam' ), 'type' => 'file']],
                ['title' => 'Pet emergencies', 'text' => 'Same-day help and an emergency number day and night.', 'url' => '/emergencies', 'file' => ['id' => $this->img( 'team-work' ), 'type' => 'file']],
            ],
        ]];
    }


    /**
     * Returns the team cards element.
     *
     * @return array<string, mixed> Cards content element
     */
    protected function team() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
            'title' => 'Meet the team',
            'columns' => '4',
            'cards' => [
                ['title' => 'Dr. Lena Brandt', 'text' => 'Vet and founder since 2001, focus on cats and surgery. At home: two Maine Coons, Pelle and Smilla.', 'file' => ['id' => $this->img( 'team-lena' ), 'type' => 'file']],
                ['title' => 'Dr. Omid Karimi', 'text' => 'Vet and partner since 2015, further training in dentistry and surgery. Comes to work with Luna, a greyhound.', 'file' => ['id' => $this->img( 'team-omid' ), 'type' => 'file']],
                ['title' => 'Dr. Priya Nair', 'text' => 'Vet since 2019, focus on puppies, kittens, small pets and ultrasound. Shares her flat with three rabbits.', 'file' => ['id' => $this->img( 'team-priya' ), 'type' => 'file']],
                ['title' => 'Rafael Santos', 'text' => 'Head veterinary nurse since 2016, anaesthesia, lab and low-stress handling. Owner of Feijão, a very calm pug.', 'file' => ['id' => $this->img( 'team-nurse' ), 'type' => 'file']],
            ],
        ]];
    }
}
