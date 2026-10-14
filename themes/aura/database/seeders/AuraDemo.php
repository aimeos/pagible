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
 * Aura theme demo for the portfolio of Noa Lindqvist, a fictional design engineer.
 */
class AuraDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    protected const DESCRIPTIONS = [
        'about' => 'Noa Lindqvist is a design engineer in Copenhagen who designs and builds interfaces, design systems and motion for digital products.',
        'animating-gradients-with-property' => 'Gradients can\'t be transitioned in CSS, until you register their colors with @property. A practical guide with three patterns.',
        'design-system-in-40-tokens' => 'How we replaced 600 design tokens with 40 and made a design system that designers and developers actually use.',
        'prototyping-motion-in-the-browser' => 'Why I stopped prototyping motion in design tools and started building throwaway prototypes in the browser.',
        'spring-physics-for-ui' => 'Springs make interfaces feel physical. Learn how stiffness, damping and mass work and write a spring in 20 lines of JavaScript.',
        'view-transitions-for-portfolios' => 'Cross-document view transitions turn a plain multi-page portfolio into something that feels like an app, with only CSS.',
        'work' => 'Selected case studies by Noa Lindqvist: design systems, banking apps, creative tools and interactive music visuals.',
        'writing' => 'Articles about interface design, CSS, motion, design systems and the space between design and code.',
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    protected const PHOTOS = [
        'code' => ['photo-1633356122544-f134324a6cee', 'Code on a monitor', 'React source code on a dark monitor'],
        'fluid' => ['photo-1557672172-298e090bd0f1', 'Fluid colors', 'Pink and blue fluid paint flowing into each other'],
        'gradient' => ['photo-1579546929518-9e396f3cc809', 'Soft gradient', 'Soft gradient from pink and orange to blue'],
        'ink' => ['photo-1604871000636-074fa5117945', 'Ink in motion', 'Dark red and blue fluid art with fine swirls'],
        'laptop' => ['photo-1517694712202-14dd9538aa97', 'Laptop with code', 'Laptop with a code editor on a dark desk'],
        'phone' => ['photo-1545235617-9465d2a55698', 'App on a phone', 'Hand holding a phone with an app in front of a laptop'],
        'sketch' => ['photo-1586717791821-3f44a563fa4c', 'Wireframe sketch', 'Hand sketching a wireframe on a tablet'],
        'swatches' => ['photo-1561070791-2526d30994b5', 'Brand colors', 'Printed brand color swatches and cards on a table'],
        'waves' => ['photo-1558591710-4b4a1ae0f04d', 'White waves', 'Abstract white waves with soft shadows'],
        'wave' => ['photo-1618005182384-a83a8bd57fbe', 'Purple wave', 'Abstract purple and blue wave gradient'],
        'wireframes' => ['photo-1541462608143-67571c6738dd', 'UI design desk', 'Desk with printed wireframes, stencils and a pencil'],
    ];

    private string $avatar;
    private string $element;


    /**
     * Creates the about page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addAbout( Page $home ) : static
    {
        $this->page( ['name' => 'About', 'title' => 'About Noa Lindqvist', 'path' => 'about'], [
            $this->heading( 'Designer who ships code', '1' ),
            $this->text( <<<'MD'
I'm a design engineer at [Northwind Studio](https://example.com) in Copenhagen. I work in the space between design and code: I sketch, prototype in the browser and stay on a project until the last easing curve is right in production.

I studied interaction design in Malmö, spent four years designing banking apps and found out that my favorite tool is a text editor. Since then I build design systems, motion and creative tools for teams who care about the details.

When I'm not at my desk, I'm on my bike along the harbor or in the darkroom developing film.
MD ),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'Experience',
                'layout' => 'vertical',
                'items' => [
                    ['label' => '2023 — now', 'title' => 'Design Engineer, Northwind Studio', 'text' => "Lead the design system and motion work for twelve product teams. Built Halo and Orbit from the first sketch to production.\n\n`Design systems` `React` `Motion`"],
                    ['label' => '2020 — 2023', 'title' => 'Senior Product Designer, Tidewater Bank', 'text' => "Redesigned the mobile banking app used by 2.4 million customers and introduced prototyping in code.\n\n`Product design` `iOS` `Prototyping`"],
                    ['label' => '2017 — 2020', 'title' => 'Interaction Designer, Studio Fjord', 'text' => "Designed installations, campaign sites and the first version of Lumen for cultural clients.\n\n`WebGL` `Creative coding`"],
                    ['label' => '2016', 'title' => 'M.A. Interaction Design, Malmö University', 'text' => 'Thesis on how motion makes interfaces easier to understand, the start of a long obsession.'],
                ],
            ]],
            $this->testimonials( 'Kind words' ),
        ], $home );

        return $this;
    }


    /**
     * Creates the work page below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addWork( Page $home ) : static
    {
        $this->page( ['name' => 'Work', 'title' => 'Selected Work', 'path' => 'work'], [
            $this->heading( 'Selected work', '1' ),
            $this->text( 'Design systems, products and creative tools from the last few years. Every project was designed and built by me together with wonderful teams.' ),
            $this->projects(),
            ['id' => Utils::uid(), 'type' => 'timeline', 'group' => 'main', 'data' => [
                'title' => 'How I work',
                'layout' => 'horizontal',
                'items' => [
                    ['label' => '01', 'title' => 'Listen', 'text' => 'Interviews with your team and users to find what really needs to change.'],
                    ['label' => '02', 'title' => 'Sketch', 'text' => 'Fast sketches and wireframes to agree on structure before pixels.'],
                    ['label' => '03', 'title' => 'Prototype', 'text' => 'Real prototypes in the browser to test motion, content and edge cases.'],
                    ['label' => '04', 'title' => 'Ship', 'text' => 'Production code and documentation your team can keep building on.'],
                ],
            ]],
            $this->cta(),
        ], $home );

        return $this;
    }


    /**
     * Creates the writing page with the blog posts below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addWriting( Page $home ) : static
    {
        $id = (string) Str::uuid7();
        $writing = $this->page( ['id' => $id, 'name' => 'Writing', 'title' => 'Writing', 'path' => 'writing'], [
            $this->heading( 'Notes on craft', '1' ),
            $this->text( 'Articles about interface design, CSS, motion and design systems. I write when a project taught me something worth sharing, about once a month.' ),
            $this->blog( '', 'list', 20, $id ),
        ], $home );

        foreach( $this->posts() as [$date, $data, $photo, $intro, $content] )
        {
            $fileId = $this->img( $photo );
            $article = $this->article( $data['title'], $intro, $fileId );
            $post = $this->page( $data + ['type' => 'blog'], array_merge( [$article], $content ), $writing, [$fileId] );

            $post->forceFill( ['created_at' => $date] )->saveQuietly();
        }

        return $this;
    }


    /**
     * Creates the avatar of Noa Lindqvist once and returns its file ID.
     *
     * @return string File ID
     */
    protected function avatar() : string
    {
        if( !isset( $this->avatar ) )
        {
            $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-labelledby="title desc">
  <title id="title">Noa Lindqvist</title>
  <desc id="desc">Violet to pink gradient circle with the white initials NL</desc>
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#6D4AFF"/>
      <stop offset="0.6" stop-color="#C04FD8"/>
      <stop offset="1" stop-color="#FF5FB1"/>
    </linearGradient>
  </defs>
  <rect width="160" height="160" fill="url(#bg)"/>
  <circle cx="124" cy="36" r="52" fill="#FFFFFF" fill-opacity="0.12"/>
  <text x="80" y="101" fill="#FFFFFF" font-family="Georgia, 'Times New Roman', serif" font-size="58" font-style="italic" letter-spacing="-2" text-anchor="middle">NL</text>
</svg>
SVG;

            $this->avatar = $this->svgFile( $svg, 'noa-lindqvist.svg', 'Noa Lindqvist', 'Violet to pink gradient circle with the white initials NL', true );
        }

        return $this->avatar;
    }


    /**
     * Returns a blog list content element.
     *
     * @param string $title List title
     * @param string $layout List layout
     * @param int $limit Maximum number of posts
     * @param string|null $parentId Parent page ID
     * @return array<string, mixed> Blog content element
     */
    protected function blog( string $title, string $layout, int $limit, ?string $parentId = null ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => array_filter( [
            'title' => $title,
            'layout' => $layout,
            'order' => '-created_at',
            'limit' => $limit,
            'parent-page' => $parentId ? ['value' => $parentId, 'label' => 'Writing'] : null,
        ] )];
    }


    /**
     * Returns a code content element.
     *
     * @param string $language Programming language
     * @param string $text Source code
     * @return array<string, mixed> Code content element
     */
    protected function code( string $language, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
            'language' => ['value' => $language],
            'text' => $text,
        ]];
    }


    /**
     * Returns the call to action content element.
     *
     * @return array<string, mixed> Call to action content element
     */
    protected function cta() : array
    {
        return ['id' => Utils::uid(), 'type' => 'cta', 'group' => 'main', 'data' => [
            'title' => 'Let\'s make something *people remember*',
            'text' => 'I take on two or three projects a year: design systems, product redesigns and creative tools. Tell me about your idea.',
            'buttons' => [
                ['label' => 'Start a project', 'url' => 'mailto:hello@example.com'],
                ['label' => 'More about me', 'url' => '/about'],
            ],
        ]];
    }


    /**
     * Creates the shared footer note and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        return $this->element ??= $this->saveElement( 'text', 'Aura footer', [
            'text' => 'Thanks for stopping by. New case studies and articles arrive every few weeks, follow along on [Dribbble](https://dribbble.com/example) or [Bluesky](https://bsky.app/profile/example.com).',
        ] );
    }


    /**
     * Returns a heading content element.
     *
     * @param string $title Heading text
     * @param string $level Heading level
     * @return array<string, mixed> Heading content element
     */
    protected function heading( string $title, string $level = '2' ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['title' => $title, 'level' => $level]];
    }


    /**
     * Returns the home page of Noa Lindqvist.
     *
     * @return Page Home page
     */
    protected function home() : Page
    {
        $elementId = $this->element();
        $fileId = $this->avatar();

        $config = [
            'website' => Validation::entry( 'website', ['title' => 'Noa Lindqvist'], 'config' ),
            'aura::profile' => [
                'type' => 'aura::profile',
                'files' => [$fileId],
                'data' => [
                    'name' => 'Noa Lindqvist',
                    'job' => 'Design Engineer',
                    'company' => 'Northwind Studio',
                    'location' => 'Copenhagen, Denmark',
                    'avatar' => ['id' => $fileId, 'type' => 'file'],
                    'bio' => 'Design engineer in Copenhagen. I design and build interfaces that feel alive and write about the craft behind them.',
                    'email' => 'hello@example.com',
                    'status' => 'Booking projects for 2027',
                    'links' => [
                        ['label' => 'Dribbble', 'url' => 'https://dribbble.com/example'],
                        ['label' => 'GitHub', 'url' => 'https://github.com/example'],
                        ['label' => 'Bluesky', 'url' => 'https://bsky.app/profile/example.com'],
                        ['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/in/example'],
                    ],
                ],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'subtitle' => 'Design Engineer · Copenhagen',
                'title' => 'I design interfaces that *feel alive*.',
                'text' => 'Hi, I\'m Noa. For ten years I have been designing and building design systems, motion and creative tools for products used by millions. I sketch, prototype and ship.',
                'buttons' => [
                    ['label' => 'See my work', 'url' => '/work'],
                    ['label' => 'Read my writing', 'url' => '/writing'],
                ],
                'files' => [['id' => $fileId, 'type' => 'file']],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'layout' => 'badges',
                'cards' => array_map( fn( $title ) => ['title' => $title], [
                    'Design systems', 'Motion design', 'Prototyping', 'Figma', 'CSS', 'React', 'Svelte',
                    'WebGL', 'Three.js', 'Accessibility', 'User research', 'Creative coding',
                ] ),
            ]],
            $this->projects( 'Selected work', 4 ),
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'In numbers',
                'layout' => 'figures',
                'columns' => '4',
                'cards' => [
                    ['title' => '10', 'text' => 'Years of designing and building interfaces'],
                    ['title' => '40+', 'text' => 'Products shipped with startups and studios'],
                    ['title' => '2.4M', 'text' => 'People using apps I designed every month'],
                    ['title' => '9', 'text' => 'Design awards, three of them for motion'],
                ],
            ]],
            $this->blog( 'Latest writing', 'cards', 3 ),
            $this->testimonials( 'Kind words' ),
            $this->cta(),
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Noa Lindqvist is a design engineer in Copenhagen who designs and builds design systems, motion and creative tools.',
                'keywords' => 'Noa Lindqvist, design engineer, portfolio, design systems, motion design, CSS, Copenhagen',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'Noa Lindqvist | Design Engineer',
                'description' => 'Interfaces that feel alive. Case studies, design systems and articles about motion and CSS.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        return $this->saveRoot( 'Noa Lindqvist | Design Engineer', $config, $meta, $content, $elementId, $fileId );
    }


    /**
     * Creates a page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page
     * @param array<int, string> $fileIds Additional file IDs
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [] ) : Page
    {
        $elementId = $this->element();
        $footer = [
            ['id' => Utils::uid(), 'type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        return $this->savePage( $data + ['lang' => 'en', 'type' => 'page', 'status' => 1], $content, $parent,
            $elementId, $this->avatar(), $footer, 'Noa Lindqvist, design engineer, portfolio, design, CSS', $fileIds );
    }


    /**
     * Builds the Aura demo page tree.
     */
    protected function pages() : void
    {
        $home = $this->home();

        $this->addWork( $home )
            ->addWriting( $home )
            ->addAbout( $home );
    }


    /**
     * Returns the blog posts as date, page data, photo key, introduction and content elements, newest first.
     *
     * @return array<int, array{0: string, 1: array<string, string>, 2: string, 3: string, 4: array<int, array<string, mixed>>}>
     */
    protected function posts() : array
    {
        return [
            ['2026-09-24 08:00:00', ['name' => 'Animating gradients', 'title' => 'Animating gradients with @property', 'path' => 'animating-gradients-with-property'], 'fluid',
                'For years, hovering a button with a gradient meant a hard jump between two backgrounds. Registering the colors as custom properties finally lets the browser animate them smoothly.', [
                $this->text( 'Browsers can transition colors, lengths and numbers, but a `background-image` is an image. When you change one gradient to another, there is nothing in between to interpolate, so the change happens in a single frame. The trick is to animate the *inputs* of the gradient instead of the gradient itself.' ),
                $this->heading( 'Register the colors' ),
                $this->text( 'With `@property` you tell the browser that a custom property is a color. From then on, it knows how to blend two values and every gradient using the property updates on each frame.' ),
                $this->code( 'css', <<<'CSS'
@property --from {
    syntax: '<color>';
    inherits: false;
    initial-value: #6D4AFF;
}

.button {
    background: linear-gradient(120deg, var(--from), #FF5FB1);
    transition: --from 0.6s ease;
}

.button:hover {
    --from: #2DD4F0;
}
CSS ),
                $this->heading( 'Rotate angles, not images' ),
                $this->text( "The same works for angles and percentages. Register an `<angle>` and animate it with keyframes to get a slowly rotating conic gradient, the glow behind the call to action on this site works exactly like that.\n\n> [!TIP]\n> Keep the number of animated properties small. Each one makes the browser repaint the whole element on every frame." ),
                $this->heading( 'Respect reduced motion' ),
                $this->text( 'Ambient animation is decoration. Wrap it in `@media (prefers-reduced-motion: no-preference)` so people who get dizzy from moving backgrounds see a calm, static gradient instead.' ),
            ]],
            ['2026-08-27 08:00:00', ['name' => 'Spring physics', 'title' => 'Spring physics for interfaces', 'path' => 'spring-physics-for-ui'], 'waves',
                'Easing curves describe time, springs describe behavior. Once you understand three numbers, your interfaces start to feel like they were made of something real.', [
                $this->text( 'A cubic bezier always takes the same time, no matter where the element starts or how fast the user flicked it. A spring doesn\'t have a duration at all. It has a target, a velocity and a few physical properties, and it simply moves until it comes to rest.' ),
                $this->heading( 'Stiffness, damping and mass' ),
                $this->text( "- **Stiffness** is how strongly the spring pulls towards the target. Higher values feel snappier.\n- **Damping** is the friction that slows it down. Low damping bounces, high damping glides.\n- **Mass** is how heavy the element feels. More mass means slower starts and longer overshoots." ),
                $this->heading( 'A spring in 20 lines' ),
                $this->code( 'javascript', <<<'JS'
function spring(el, target, { stiffness = 170, damping = 26, mass = 1 } = {}) {
    let position = 0, velocity = 0, last = performance.now();

    function step(now) {
        const dt = Math.min((now - last) / 1000, 1 / 30);
        const force = -stiffness * (position - target) - damping * velocity;

        velocity += (force / mass) * dt;
        position += velocity * dt;
        last = now;

        el.style.transform = `translateX(${position}px)`;

        if (Math.abs(velocity) > 0.01 || Math.abs(position - target) > 0.01) {
            requestAnimationFrame(step);
        }
    }

    requestAnimationFrame(step);
}
JS ),
                $this->text( 'Start with stiffness 170 and damping 26 for most interface movement. Use lower damping only for playful moments, like a confirmation or an empty state. Bouncy navigation gets tiring after the third click.' ),
            ]],
            ['2026-07-30 08:00:00', ['name' => 'A design system in 40 tokens', 'title' => 'A design system in 40 tokens', 'path' => 'design-system-in-40-tokens'], 'wireframes',
                'Our design system had 600 tokens and nobody knew which one to use. We deleted 560 of them and adoption doubled within three months.', [
                $this->text( 'Tokens are meant to encode decisions. When you have a token for every hex value in the brand guide, you haven\'t made a decision, you have only moved the problem from the design file to the codebase.' ),
                $this->heading( 'Three layers, few names' ),
                $this->text( "We rebuilt Halo around three layers:\n\n1. **Palette:** raw values that nobody uses directly.\n2. **Roles:** `surface`, `text`, `accent`, `border` and their states, 32 tokens in total.\n3. **Scales:** eight spacing and radius steps that cover every layout." ),
                $this->code( 'css', <<<'CSS'
:root {
    --surface: #FFFFFF;
    --surface-raised: #F7F6FB;
    --text: #2A2838;
    --text-muted: #6A687C;
    --accent: #5B3DF5;
    --border: #E3E1EE;
}

[data-theme=dark] {
    --surface: #08080D;
    --surface-raised: #12111B;
    --text: #C9C8D8;
    --text-muted: #8D8BA3;
    --accent: #A193FF;
    --border: #22212E;
}
CSS ),
                $this->heading( 'Measure usage, not coverage' ),
                $this->text( 'We added a small lint rule that counts raw color values in pull requests. Instead of blocking them, it posts a friendly comment with the closest token. Within three months raw values dropped by 80%, without a single argument in code review.' ),
            ]],
            ['2026-06-18 08:00:00', ['name' => 'View transitions', 'title' => 'View transitions for portfolios', 'path' => 'view-transitions-for-portfolios'], 'code',
                'You don\'t need a single-page app to get smooth page changes anymore. Two lines of CSS make a multi-page portfolio feel like one continuous canvas.', [
                $this->text( 'Single-page apps became popular in portfolios mostly because of transitions: the project image grows into the case study, the header stays where it is. With cross-document view transitions, the browser does this for normal links between normal pages.' ),
                $this->heading( 'Opt in on both pages' ),
                $this->code( 'css', <<<'CSS'
@view-transition {
    navigation: auto;
}

.project-cover {
    view-transition-name: cover;
}
CSS ),
                $this->text( "Both the old and the new page need the `@view-transition` rule. Elements with the same `view-transition-name` on both pages morph into each other, everything else cross-fades.\n\n> [!IMPORTANT]\n> Each `view-transition-name` must be unique on a page. Set it only on the cover of the project the user clicked, not on every card in the grid." ),
                $this->heading( 'Keep it fast' ),
                $this->text( 'The browser waits for the new page before it animates. A slow page makes every transition feel sluggish, so the transition is also a nice reminder to keep your pages light.' ),
            ]],
            ['2026-05-21 08:00:00', ['name' => 'Prototyping motion', 'title' => 'Prototyping motion in the browser', 'path' => 'prototyping-motion-in-the-browser'], 'laptop',
                'Motion that looks great in a design tool often feels wrong on a real device. I stopped handing over videos and started handing over throwaway prototypes.', [
                $this->text( 'A video shows one path through an interaction at one speed. Real people interrupt animations, scroll while they run and use slow phones. You only discover how the motion behaves when you can touch it.' ),
                $this->heading( 'Throwaway on purpose' ),
                $this->text( "My prototypes are single HTML files with no build step. They are ugly inside and that's the point: nobody is tempted to ship them, and I can try ten variants in an afternoon.\n\n- One file per idea, named after the question it answers\n- Sliders for every timing value so the team can tune it live\n- Tested on the cheapest phone in the office" ),
                $this->heading( 'Hand over numbers, not videos' ),
                $this->text( 'When a prototype feels right, the final values go into the design system as motion tokens. Developers get durations, curves and spring settings they can copy, and the prototype stays in the repository as living documentation.' ),
            ]],
        ];
    }


    /**
     * Returns the project cards content element.
     *
     * @param string $title Section title
     * @param int $limit Maximum number of projects
     * @return array<string, mixed> Cards content element
     */
    protected function projects( string $title = '', int $limit = 6 ) : array
    {
        $projects = [
            ['swatches', 'Halo design system', "Forty tokens, 60 components and motion guidelines used by twelve product teams.\n\n`Design system` `React` `2025`"],
            ['phone', 'Tidewater banking app', "A calm mobile bank for 2.4 million customers with gestures that feel natural.\n\n`Product design` `iOS` `2023`"],
            ['gradient', 'Lumen', "A gradient generator with live mesh editing and CSS export, used by 80,000 designers.\n\n`Creative tool` `WebGL` `2024`"],
            ['sketch', 'Fieldnotes wireframe kit', "Hand-drawn wireframe components for early ideas that nobody mistakes for final designs.\n\n`Figma` `Open source` `2022`"],
            ['wave', 'Orbit motion library', "Springs, sequences and reduced motion fallbacks for the web in 4 kB.\n\n`TypeScript` `Motion` `2026`"],
            ['ink', 'Nordlys', "Interactive northern lights that react to live music for a Copenhagen concert hall.\n\n`Three.js` `Audio` `2021`"],
        ];

        return ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => array_filter( [
            'title' => $title,
            'cards' => array_map( fn( $item ) => [
                'title' => $item[1],
                'url' => '/work',
                'text' => $item[2],
                'file' => ['id' => $this->img( $item[0] ), 'type' => 'file'],
            ], array_slice( $projects, 0, $limit ) ),
        ] )];
    }


    /**
     * Returns the testimonials content element.
     *
     * @param string $title Section title
     * @return array<string, mixed> Testimonial content element
     */
    protected function testimonials( string $title ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
            'title' => $title,
            'items' => [
                ['text' => 'Noa turned our design system from a PDF nobody read into something every team uses daily. Their prototypes ended arguments in minutes.', 'name' => 'Ingrid Dahl', 'role' => 'VP Design, Tidewater Bank'],
                ['text' => 'The rare designer who cares about the last 10%. Our app reviews mention the animations by name.', 'name' => 'Marcus Feld', 'role' => 'CTO, Northwind Studio'],
                ['text' => 'Working with Noa felt like working with a designer and a senior engineer at once. Nothing got lost in the handover, because there was none.', 'name' => 'Aiko Tanaka', 'role' => 'Product Lead, Lumen'],
            ],
        ]];
    }


    /**
     * Returns a text content element.
     *
     * @param string $text Markdown text
     * @return array<string, mixed> Text content element
     */
    protected function text( string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => ['text' => $text]];
    }
}
