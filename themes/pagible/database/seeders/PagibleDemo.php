<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Database\Seeders;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


/**
 * Pagible theme demo content for the PagibleAI CMS product site.
 */
class PagibleDemo extends AbstractDemo
{
    /** @var array<string, string> Meta descriptions keyed by page path */
    private const DESCRIPTIONS = [
        'a-draft-should-never-overwrite-a-live-page' => 'Keep live pages stable while editors prepare, review, schedule, and restore immutable content versions in PagibleAI CMS.',
        'give-structured-content-a-shape-editors-can-use' => 'Design structured content models with clear fields, useful constraints, and stable contracts for editors, Blade views, and API clients.',
        'where-ai-belongs-in-editorial-work' => 'Use AI for drafting, translation, transcription, and media work while keeping editorial review and publishing decisions human.',
        'one-content-base-several-delivery-paths' => 'Deliver one published content base through Blade themes, JSON:API, GraphQL, and MCP without duplicating pages across clients.',
        'docs/build-a-content-element' => 'Define a reusable PagibleAI content element in JSON schema, expose clear fields to editors, and render the published data with Blade.',
        'docs/configure-editorial-ai' => 'Configure separate AI providers for writing, translation, transcription, descriptions, and image tasks while preserving human review.',
    ];

    /**
     * Curated Unsplash photos used across the Pagible demo.
     *
     * @var array<string, array{0: string, 1: string, 2: string}>
     */
    private const PHOTOS = [
        'api' => ['photo-1558494949-ef010cbdcc31', 'Content delivery infrastructure', 'Server infrastructure used to deliver content through web APIs'],
        'content' => ['photo-1497366811353-6870744d04b2', 'Structured content planning', 'Editorial workspace prepared for structured content planning'],
        'delivery' => ['photo-1754039984985-ef607d80113a', 'Content delivery code', 'Website delivery code displayed across several screens in a dark workspace'],
        'editor' => ['photo-1498050108023-c5249f4df085', 'PagibleAI editing workspace', 'Developer and editor workspace for building a Laravel website'],
        'global' => ['photo-1451187580459-43490279c0fa', 'Global publishing network', 'Connected network representing multilingual content delivery'],
        'hero-site' => ['photo-1560472355-109703aa3edc', 'Website editing screen', 'Website displayed on a desktop monitor in a working office'],
        'hero-team' => ['photo-1758873268745-dd2cf0d677b5', 'Team working in PagibleAI', 'Editorial and development team collaborating around one computer'],
        'interfaces' => ['photo-1755997268713-0ef1cc938cb6', 'Content across interfaces', 'Editor working across several screens and digital interfaces'],
        'media' => ['photo-1488590528505-98d2b5aba04b', 'Media library', 'Digital media workspace with connected hardware'],
        'model' => ['photo-1518770660439-4636190af475', 'Content model', 'Detailed system architecture representing a structured content model'],
        'modeling' => ['photo-1531403009284-440f080d1e12', 'Structured content workshop', 'Team organizing content and interface ideas on a planning board'],
        'publish' => ['photo-1450101499163-c8848c66ca85', 'Publishing review', 'Documents and notes prepared for an editorial publishing review'],
        'search' => ['photo-1551288049-bebda4e38f71', 'Content search', 'Search and reporting interface displayed on a laptop'],
        'security' => ['photo-1563986768609-322da13575f3', 'CMS access controls', 'Security controls used to protect an editorial workspace'],
        'team' => ['photo-1521737711867-e3b97375f902', 'Editorial team', 'Editorial and development team reviewing a website together'],
        'version' => ['photo-1556075798-4825dfaaf498', 'Content version history', 'Version history displayed in a development workspace'],
        'workflow' => ['photo-1552664730-d307ca884978', 'Editorial workflow', 'Team discussing an editorial workflow around a shared table'],
    ];

    /** @var array<string, string> File IDs for fixed-ratio card images */
    private array $cardImages = [];
    private string $element;
    private string $guideFile;
    private string $logoFile;


    /**
     * Creates the journal section below the home page.
     *
     * @param Page $home Home page
     * @param string $blogId Journal page ID referenced by listing elements
     * @return static Same object for fluent calls
     */
    protected function addBlog( Page $home, string $blogId ) : static
    {
        $cover = $this->img( 'editor' );

        $blog = $this->page( [
            'id' => $blogId,
            'lang' => 'en',
            'name' => 'Journal',
            'title' => 'PagibleAI Journal',
            'path' => 'blog',
            'tag' => 'blog',
            'type' => 'blog',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'Notes on running a modern content stack',
                'subtitle' => 'PagibleAI Journal',
                'text' => 'Practical writing about editorial systems, structured content, Laravel delivery, and the careful use of AI inside a publishing workflow.',
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'Recent articles',
                'layout' => 'list',
                'limit' => 4,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Journal'],
            ]],
        ], $home, [], [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'The PagibleAI journal covers editorial workflows, structured content, Laravel delivery, and responsible AI assistance.',
                'keywords' => 'PagibleAI CMS journal, Laravel CMS, structured content, editorial workflow',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'PagibleAI Journal',
                'description' => 'Working notes for editors and developers who share responsibility for a website.',
                'file' => ['id' => $cover, 'type' => 'file'],
            ], 'meta' ),
        ] );

        $this->page( [
            'lang' => 'en',
            'name' => 'Drafts must never replace live pages',
            'title' => 'Drafts Must Never Replace Live Pages',
            'path' => 'a-draft-should-never-overwrite-a-live-page',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Drafts must never replace live pages',
                "A routine edit should not put the public site at risk. Editors need room to revise a page, compare versions, and ask for review while readers continue to see the approved copy.\n\nPagibleAI stores each revision as an immutable snapshot. The editor works on the latest version; the website reads the published one. Those two states only meet when someone with the right permission publishes the draft.",
                $this->img( 'version' )
            ),
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Treat publishing as a decision',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'publish' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "A useful review shows the proposed content, the person who changed it, and the version currently online. The reviewer should not have to reconstruct that history from messages or browser tabs.\n\nPagibleAI keeps drafts, scheduled versions, published snapshots, and restored revisions in the same record. You can move forward or restore an earlier version without changing the audit trail.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'What each state is for',
                'header' => 'row',
                'table' => [
                    ['State', 'Who sees it', 'Typical use'],
                    ['Draft', 'Editors with access', 'Writing, layout changes, internal review'],
                    ['Scheduled', 'Editors until its publish time', 'Campaigns and dated announcements'],
                    ['Published', 'Public readers and delivery APIs', 'The approved website'],
                    ['Restored', 'Editors until republished', 'Returning to a known revision'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "This separation becomes more valuable as the team grows. Writers can keep moving, publishers retain control, and developers do not need to invent a parallel staging system for ordinary content changes.",
            ]],
            $this->articleHero( 'Keep the live page stable', 'Set up a publishing flow where drafts remain editable and every public change has a clear version.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Give structured content a shape editors can use',
            'title' => 'Give Structured Content a Shape Editors Can Use',
            'path' => 'give-structured-content-a-shape-editors-can-use',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Give structured content a shape editors can use',
                "A content model is only useful when an editor can understand it without reading the implementation. Field names should match the job at hand. Constraints should prevent real mistakes. Optional fields should remain optional.\n\nPagibleAI defines elements in JSON schema and renders them with Blade. Editors get a focused form; developers keep a small, reviewable contract between stored content and the frontend.",
                $this->img( 'modeling' )
            ),
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"testimonial\": {\n    \"label\": \"Testimonial\",\n    \"fields\": {\n      \"quote\": {\"type\": \"text\", \"required\": true},\n      \"name\": {\"type\": \"string\", \"required\": true},\n      \"role\": {\"type\": \"string\"},\n      \"image\": {\"type\": \"image\"}\n    }\n  }\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'A model has three readers',
                'cards' => [
                    ['title' => 'The editor', 'text' => 'Needs plain labels, sensible defaults, and enough structure to avoid cleanup later.', 'file' => ['id' => $this->cardImg( 'content' ), 'type' => 'file']],
                    ['title' => 'The frontend', 'text' => 'Needs a stable data shape that a Blade view or API client can render without guesswork.', 'file' => ['id' => $this->cardImg( 'editor' ), 'type' => 'file']],
                    ['title' => 'The next developer', 'text' => 'Needs a schema small enough to review and extend without tracing hidden conventions.', 'file' => ['id' => $this->cardImg( 'team' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "Start with the content that must remain reusable: headings, text, media, links, and repeatable items. Add presentation choices only when an editor genuinely needs them. A narrow model is easier to migrate, translate, search, and deliver through an API.",
            ]],
            $this->articleHero( 'Model the content your team already understands', 'Follow a concrete example from schema definition to editable field and Blade output.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'Where AI belongs in editorial work',
            'title' => 'Where AI Belongs in Editorial Work',
            'path' => 'where-ai-belongs-in-editorial-work',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'Where AI belongs in editorial work',
                "AI is useful when it removes a mechanical step and leaves the editorial decision visible. It is less useful when it hides where a claim came from or turns a specific page into interchangeable copy.\n\nPagibleAI places writing, refinement, translation, image work, and transcription inside the editor. The result is still a draft. You review it in context and publish it through the same versioned workflow as any other change.",
                $this->img( 'workflow' )
            ),
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Use assistance where review is straightforward',
                'header' => 'row',
                'table' => [
                    ['Task', 'A useful starting point', 'Editorial check'],
                    ['Drafting', 'Outline or first paragraph from an approved brief', 'Facts, voice, and omissions'],
                    ['Translation', 'A complete draft in the target language', 'Terminology, locale, and links'],
                    ['Image editing', 'Crop, background removal, or extension', 'Rights, accuracy, and composition'],
                    ['Description', 'Alt text or media summary', 'What the image contributes in context'],
                    ['Transcription', 'Timed speech converted to text', 'Names, numbers, and technical terms'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Keep providers interchangeable',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'media' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "Different jobs call for different providers. PagibleAI lets you configure writing, translation, transcription, description, and image operations separately. You can change the provider or model for one task without rebuilding the editorial interface.\n\nThat boundary also keeps credentials and operational choices in Laravel configuration, where your development team can review them.",
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "The measure of a good integration is not how often the model appears. It is whether the editor reaches a stronger draft with less copying between tools, while retaining a clear point at which a person accepts the work.",
            ]],
            $this->articleHero( 'Configure assistance around your workflow', 'Choose providers per task and keep generated work inside the normal review and publishing path.' ),
        ], $blog );

        $this->page( [
            'lang' => 'en',
            'name' => 'One content base, several delivery paths',
            'title' => 'One Content Base, Several Delivery Paths',
            'path' => 'one-content-base-several-delivery-paths',
            'tag' => 'article',
            'type' => 'blog',
            'status' => 1,
        ], [
            $this->article(
                'One content base, several delivery paths',
                "The website, a mobile client, an internal tool, and an automation agent should not need separate copies of the same page. They need interfaces suited to their jobs, backed by one published content record.\n\nPagibleAI renders Laravel sites with Blade, exposes published content through a read-only JSON:API, provides GraphQL for administration, and includes MCP tools for controlled content operations.",
                $this->img( 'interfaces' )
            ),
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Choose the interface by responsibility',
                'header' => 'row',
                'table' => [
                    ['Interface', 'Best suited to', 'Access pattern'],
                    ['Blade themes', 'Laravel websites and server-rendered pages', 'Published page tree'],
                    ['JSON:API', 'Apps and frontend clients', 'Read-only published content'],
                    ['GraphQL', 'Admin interfaces and integrations', 'Queries and controlled mutations'],
                    ['MCP tools', 'Agent-assisted content operations', 'Permission-checked CMS actions'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'graphql'],
                'text' => "query PublishedPage {\n  page(path: \"company/about\") {\n    id\n    title\n    lang\n    content\n    files { id mime path description }\n  }\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Preserve one publishing boundary',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'global' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Each delivery path should respect the same published version, tenant boundary, language, and permissions. That gives editors one place to correct content and gives developers an explicit contract for every consumer.\n\nWhen a page is published, search and delivery clients can work from the approved snapshot rather than an editor's unfinished revision.",
            ]],
            $this->articleHero( 'Pick the right delivery surface', 'See how the theme renderer, JSON:API, GraphQL, and MCP packages divide their responsibilities.' ),
        ], $blog );

        return $this;
    }


    /**
     * Creates the documentation section below the home page.
     *
     * @param Page $home Home page
     * @return static Same object for fluent calls
     */
    protected function addDocs( Page $home ) : static
    {
        $diagram = $this->img( 'model' );

        $docs = $this->page( [
            'lang' => 'en',
            'name' => 'Documentation',
            'title' => 'PagibleAI CMS Documentation',
            'path' => 'docs',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => [
                'title' => 'On this page',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Install PagibleAI in a Laravel application',
            ]],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI installs into an existing Laravel 11, 12, or 13 application. The installer adds the CMS packages and configuration; migrations create the page, element, file, version, and search tables.\n\nStart with a clean branch and a database backup when adding the CMS to an established application. The package uses your Laravel database, filesystem, queue, cache, authentication, and deployment process.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "composer require aimeos/pagible\nphp artisan cms:install\nphp artisan migrate\nphp artisan cms:user -e editor@example.com",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Package responsibilities',
                'header' => 'row',
                'table' => [
                    ['Package', 'Responsibility', 'Use it when'],
                    ['Core', 'Models, versions, permissions, tenancy', 'Every installation'],
                    ['Admin', 'Vue editing interface', 'People edit content in a browser'],
                    ['Theme', 'Blade rendering and page cache', 'Laravel serves the website'],
                    ['GraphQL', 'Administrative API', 'An application manages CMS records'],
                    ['JSON:API', 'Published content delivery', 'A frontend reads content remotely'],
                    ['AI', 'Writing, translation, media operations', 'Editors need configured assistance'],
                    ['Search', 'Database-native full-text index', 'Readers search the website'],
                    ['MCP', 'Permission-checked content tools', 'Agents assist with CMS work'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Working examples',
                'cards' => [
                    ['title' => 'Build a content element', 'text' => "Define one reusable content shape, render it with Blade, and make it available to editors.\n\n[Open the content element example](/docs/build-a-content-element)", 'file' => ['id' => $this->img( 'content' ), 'type' => 'file']],
                    ['title' => 'Configure editorial AI', 'text' => "Assign a provider and model to each editorial task without coupling the CMS to one service.\n\n[Open the AI configuration example](/docs/configure-editorial-ai)", 'file' => ['id' => $this->img( 'workflow' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Publish with explicit roles',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'publish' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "PagibleAI separates viewing, editing, publishing, and administration permissions. Named role definitions expand into permission sets, so a small site can use simple roles while a larger installation can assign narrower capabilities.\n\nEvery save creates a version. Publishing selects the snapshot that public routes and delivery APIs may read. Schedule the `cms:publish` command when your team uses timed releases.",
            ]],
            ['id' => Utils::uid(), 'type' => 'file', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->guideFile(), 'type' => 'file'],
            ]],
        ], $home, [$diagram], [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'Install PagibleAI CMS, choose packages, define content, configure permissions, and publish from a Laravel application.',
                'keywords' => 'PagibleAI documentation, Laravel CMS installation, structured content, CMS API',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'PagibleAI CMS Documentation',
                'description' => 'Installation and working examples for editors and Laravel developers.',
                'file' => ['id' => $diagram, 'type' => 'file'],
            ], 'meta' ),
        ] );

        $this->page( [
            'lang' => 'en',
            'name' => 'Build a content element',
            'title' => 'Build a Content Element | PagibleAI Documentation',
            'path' => 'docs/build-a-content-element',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => ['title' => 'On this page']],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Define the editor contract']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "A content element starts with a schema entry. The schema names the element, groups its fields, and tells the admin which input control to show. Keep the stored data about meaning rather than layout whenever possible.\n\nThe example below defines a release note with a title, summary, release date, and optional link. It is narrow enough for an editor to complete correctly and stable enough for several frontends to consume.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'json'],
                'text' => "{\n  \"release-note\": {\n    \"label\": \"Release note\",\n    \"fields\": {\n      \"title\": {\"type\": \"string\", \"required\": true},\n      \"summary\": {\"type\": \"markdown\", \"required\": true},\n      \"released\": {\"type\": \"date\", \"required\": true},\n      \"url\": {\"type\": \"url\"}\n    }\n  }\n}",
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Render the published data']],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'html'],
                'text' => <<<'HTML'
<article class="release-note">
  <time datetime="{{ cms($page, 'data.released') }}">
    {{ cms($page, 'data.released') }}
  </time>
  <h2>{{ cms($page, 'data.title') }}</h2>
  <div>{!! cmsmarkdown(cms($page, 'data.summary')) !!}</div>
</article>
HTML,
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Review before adding fields',
                'header' => 'row',
                'table' => [
                    ['Question', 'Prefer', 'Avoid'],
                    ['Does the field describe meaning?', 'released, summary, author', 'leftColumn, blueText'],
                    ['Can an editor choose correctly?', 'A short list of valid options', 'An unexplained free-form code'],
                    ['Will clients reuse it?', 'A stable value and explicit type', 'Markup tied to one template'],
                    ['Is it actually required?', 'Required only when rendering depends on it', 'Mandatory fields added for completeness'],
                ],
            ]],
        ], $docs );

        $this->page( [
            'lang' => 'en',
            'name' => 'Configure editorial AI',
            'title' => 'Configure Editorial AI | PagibleAI Documentation',
            'path' => 'docs/configure-editorial-ai',
            'type' => 'docs',
            'status' => 1,
        ], [
            ['id' => Utils::uid(), 'type' => 'toc', 'group' => 'main', 'data' => ['title' => 'On this page']],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => ['level' => 2, 'title' => 'Configure tasks separately']],
            ['id' => Utils::uid(), 'type' => 'text', 'group' => 'main', 'data' => [
                'text' => "PagibleAI does not require one provider to handle every editorial task. Configure the service and model for writing, refinement, translation, descriptions, transcription, and image operations independently. Only add credentials for the features you enable.\n\nKeep secrets in environment variables. Commit the provider and model choices only when they are safe defaults for every deployment.",
            ]],
            ['id' => Utils::uid(), 'type' => 'code', 'group' => 'main', 'data' => [
                'language' => ['value' => 'bash'],
                'text' => "CMS_AI_WRITE=gemini\nCMS_AI_WRITE_MODEL=gemini-2.5-flash\nCMS_AI_WRITE_API_KEY=your-key\n\nCMS_AI_TRANSLATE=deepl\nCMS_AI_TRANSLATE_API_KEY=your-key\n\nCMS_AI_TRANSCRIBE=openai\nCMS_AI_TRANSCRIBE_MODEL=whisper-1\nCMS_AI_TRANSCRIBE_API_KEY=your-key",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'Operational checks',
                'header' => 'row',
                'table' => [
                    ['Check', 'Why it matters', 'Owner'],
                    ['Provider terms', 'Content may leave your infrastructure', 'Legal or security'],
                    ['Model choice', 'Quality, cost, and context limits vary', 'Product owner'],
                    ['Key scope', 'Limits damage from a leaked credential', 'Platform team'],
                    ['Human review', 'Generated work remains a draft', 'Publisher'],
                    ['Fallback behavior', 'Editors need a clear error when a service is unavailable', 'Development team'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'main', 'data' => [
                'level' => 2,
                'title' => 'Keep the publishing decision human',
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'security' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "Generated text, translations, and media enter the same draft and version workflow as manual changes. The provider does not publish a page. Your CMS permissions still determine who can approve the result and make it public.\n\nTest the configured task with representative content before enabling it for a wider editorial group.",
            ]],
        ], $docs );

        return $this;
    }


    /**
     * Creates an article lead element with the file reference used by previews.
     *
     * @param string $title Article title
     * @param string $text Article introduction
     * @param string $fileId Cover file ID
     * @return array<string, mixed> Article content element
     */
    protected function article( string $title, string $text, string $fileId ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'article', 'group' => 'main', 'files' => [$fileId], 'data' => [
            'title' => $title,
            'file' => ['id' => $fileId, 'type' => 'file'],
            'text' => $text,
        ]];
    }


    /**
     * Creates the closing call-to-action hero for an article.
     *
     * @param string $title Hero title
     * @param string $text Hero text
     * @return array<string, mixed> Hero content element
     */
    protected function articleHero( string $title, string $text ) : array
    {
        return ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
            'title' => $title,
            'subtitle' => 'PagibleAI in practice',
            'text' => $text,
            'url' => '/docs',
            'button' => 'Open the documentation',
        ]];
    }


    /**
     * Creates a fixed 3:2 card image and returns its file ID.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function cardImg( string $key ) : string
    {
        if( !isset( $this->cardImages[$key] ) )
        {
            [$photo, $name, $desc] = self::PHOTOS[$key];
            $base = 'https://images.unsplash.com/' . $photo;
            $url = fn( int $w, int $h ) => $base . '?w=' . $w . '&h=' . $h . '&q=80&fm=jpg&fit=crop';

            $data = [
                'mime' => 'image/jpeg',
                'lang' => 'en',
                'name' => $name,
                'path' => $url( 1500, 1000 ),
                'previews' => ['500' => $url( 500, 333 ), '1000' => $url( 1000, 667 )],
                'description' => ['en' => $desc],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->cardImages[$key] = (string) $file->refresh()->id;
        }

        return $this->cardImages[$key];
    }


    /**
     * Creates the shared PagibleAI footer element and returns its ID.
     *
     * @return string Element ID
     */
    protected function element() : string
    {
        if( !isset( $this->element ) )
        {
            $cards = [
                ['title' => 'Product', 'text' => "- [Platform](/)\n- [Documentation](/docs)\n- [GitHub](https://github.com/aimeos/pagible)"],
                ['title' => 'For developers', 'text' => "- [Build a content element](/docs/build-a-content-element)\n- [Configure editorial AI](/docs/configure-editorial-ai)\n- [Journal](/blog)"],
                ['title' => 'Project', 'text' => "- MIT licensed\n- Laravel 11, 12 and 13\n- PHP 8.2+"],
            ];

            $element = Element::forceCreate( [
                'lang' => 'en',
                'type' => 'cards',
                'name' => 'PagibleAI footer',
                'data' => ['type' => 'cards', 'data' => ['cards' => $cards]],
                'editor' => 'demo',
            ] );

            $version = $element->versions()->forceCreate( [
                'lang' => 'en',
                'data' => [
                    'lang' => 'en',
                    'type' => 'cards',
                    'name' => 'PagibleAI footer',
                    'data' => ['cards' => $cards],
                ],
                'editor' => 'demo',
            ] );

            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $element->publish( $version );
            $this->element = (string) $element->refresh()->id;
        }

        return $this->element;
    }


    /**
     * Returns the ID of the primary shared demo image.
     *
     * @return string File ID
     */
    protected function file() : string
    {
        return $this->img( 'editor' );
    }


    /**
     * Creates a downloadable implementation guide and returns its ID.
     *
     * @return string File ID
     */
    protected function guideFile() : string
    {
        if( !isset( $this->guideFile ) )
        {
            $data = [
                'mime' => 'application/pdf',
                'lang' => 'en',
                'name' => 'PagibleAI implementation checklist',
                'path' => 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
                'previews' => [],
                'description' => ['en' => 'Downloadable checklist for planning a PagibleAI CMS installation'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->guideFile = (string) $file->refresh()->id;
        }

        return $this->guideFile;
    }


    /**
     * Creates the PagibleAI home page and returns it.
     *
     * @param string $blogId Journal page ID referenced by listing elements
     * @return Page Home page
     */
    protected function home( string $blogId ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $logoId = $this->logoFile();

        $config = [
            'logo' => [
                'type' => 'logo',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
            'logo-alternative' => [
                'type' => 'logo-alternative',
                'files' => [$logoId],
                'data' => ['file' => ['id' => $logoId, 'type' => 'file']],
            ],
        ];

        $content = [
            ['id' => Utils::uid(), 'type' => 'hero', 'group' => 'main', 'data' => [
                'title' => 'A Laravel CMS your whole team can work with',
                'subtitle' => 'PagibleAI CMS',
                'text' => 'Give editors a clear publishing system and developers a compact, API-first foundation that fits the Laravel application you already run.',
                'url' => '/docs',
                'button' => 'Install PagibleAI',
                'background' => ['id' => $this->img( 'hero-site' ), 'type' => 'file'],
                'background-animation' => 'zoom',
                'files' => [
                    ['id' => $this->img( 'hero-team' ), 'type' => 'file'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'One system, clear responsibilities',
                'cards' => [
                    ['title' => 'Editors shape and publish', 'text' => 'Build pages from defined content elements, keep drafts separate from the live site, schedule releases, and restore earlier versions.', 'file' => ['id' => $this->img( 'workflow' ), 'type' => 'file']],
                    ['title' => 'Developers keep Laravel', 'text' => 'Use Blade, Eloquent, queues, storage, cache, authentication, and the deployment practices already understood by your team.', 'file' => ['id' => $this->img( 'editor' ), 'type' => 'file']],
                    ['title' => 'Clients use published content', 'text' => 'Serve the website from a theme or deliver the same approved records through JSON:API and purpose-built integrations.', 'file' => ['id' => $this->img( 'delivery' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'publish' ), 'type' => 'file'],
                'position' => 'start',
                'ratio' => '1-2',
                'text' => "## Let editors work without touching the live version\n\nEvery change becomes an immutable version. Editors see the latest draft while public routes and delivery APIs continue to read the published snapshot. A publisher can review, schedule, restore, or approve the exact revision that should go online.\n\nShared elements keep recurring content in one place, and the page tree gives complex sites a navigation structure that remains understandable in the admin.",
            ]],
            ['id' => Utils::uid(), 'type' => 'table', 'group' => 'main', 'data' => [
                'title' => 'One content base, several ways to use it',
                'header' => 'row',
                'table' => [
                    ['Surface', 'What it provides', 'Typical reader'],
                    ['Theme renderer', 'Cached HTML from Blade views', 'Website visitors'],
                    ['JSON:API', 'Read-only published records', 'Web and mobile clients'],
                    ['GraphQL', 'Queries and CMS mutations', 'Admin applications and integrations'],
                    ['MCP server', '33 content and media tools', 'Authorized assistants and automations'],
                    ['Search engine', 'Database-native full-text search', 'Visitors and editors'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'image-text', 'group' => 'main', 'data' => [
                'file' => ['id' => $this->img( 'media' ), 'type' => 'file'],
                'position' => 'end',
                'ratio' => '1-2',
                'text' => "## Put AI beside the editorial controls\n\nEditors can draft and refine text, translate content, describe media, transcribe audio, and perform focused image operations without moving work through unrelated tools. Each task can use its own configured provider and model.\n\nAssisted work enters the page as a draft. Your permissions, review process, and published version remain the authority.",
            ]],
            ['id' => Utils::uid(), 'type' => 'cards', 'group' => 'main', 'data' => [
                'title' => 'Built for the work around publishing',
                'cards' => [
                    ['title' => 'Multiple sites and languages', 'text' => 'Run separate page trees by domain, keep tenant data scoped in one shared database, and publish localized content from the same model.', 'file' => ['id' => $this->img( 'global' ), 'type' => 'file']],
                    ['title' => 'Search on your database', 'text' => 'Use SQLite FTS5, MySQL full text, PostgreSQL tsvector, or SQL Server CONTAINSTABLE through one Scout engine.', 'file' => ['id' => $this->img( 'search' ), 'type' => 'file']],
                    ['title' => 'Security in the normal path', 'text' => 'Sanitize submitted HTML, enforce CSP headers, validate URLs, throttle endpoints, and apply tenant-aware permissions.', 'file' => ['id' => $this->img( 'security' ), 'type' => 'file']],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'testimonial', 'group' => 'main', 'data' => [
                'title' => 'Made for teams that share ownership of the site',
                'items' => [
                    ['name' => 'Leonie Hartmann', 'role' => 'Editorial Director, Kante Studio', 'text' => 'Our editors can prepare a release without wondering what is already public. The version boundary is obvious, and the page tree feels familiar on the first day.'],
                    ['name' => 'Samir Patel', 'role' => 'Lead Developer, Northstar Works', 'text' => 'We kept Laravel in charge of the application and added the CMS where it belonged. There was no second platform to operate or work around.'],
                    ['name' => 'Maya Chen', 'role' => 'Content Operations, Relay House', 'text' => 'Translation and media descriptions now happen inside the draft. Review still belongs to our team, which made the rollout easy to explain.'],
                ],
            ]],
            ['id' => Utils::uid(), 'type' => 'blog', 'group' => 'main', 'data' => [
                'title' => 'From the journal',
                'layout' => 'cards',
                'limit' => 2,
                'order' => '_lft',
                'parent-page' => ['value' => $blogId, 'label' => 'Journal'],
            ]],
            ['id' => Utils::uid(), 'type' => 'questions', 'group' => 'main', 'data' => [
                'title' => 'Before you install',
                'items' => [
                    ['title' => 'Can PagibleAI be added to an existing Laravel application?', 'text' => 'Yes. PagibleAI is installed as Composer packages and uses the application\'s existing database, storage, cache, queue, and authentication services.'],
                    ['title' => 'Does AI-generated content publish automatically?', 'text' => 'No. Generated and translated content remains part of the editable draft. Publishing still requires the permissions and action defined by your workflow.'],
                    ['title' => 'Can one installation serve several sites?', 'text' => 'Yes. Multi-domain routing can select separate page trees, and single-database tenant scopes keep records separated when you run more than one tenant.'],
                    ['title' => 'Which databases support full-text search?', 'text' => 'The search package supports SQLite, MySQL, PostgreSQL, and SQL Server using the native full-text features of each database.'],
                    ['title' => 'Is PagibleAI open source?', 'text' => 'Yes. PagibleAI CMS is released under the MIT license.'],
                ],
            ]],
            ['id' => 'contact', 'type' => 'contact', 'group' => 'main', 'data' => [
                'title' => 'Tell us what your publishing workflow needs',
            ]],
            ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'PagibleAI CMS']],
            ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'],
        ];

        $meta = [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => 'PagibleAI is an API-first Laravel CMS for structured content, versioned publishing, AI-assisted editorial work, themes, search, and multi-site delivery.',
                'keywords' => 'PagibleAI CMS, Laravel CMS, API-first CMS, structured content, AI content management',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => 'PagibleAI CMS for Laravel',
                'description' => 'A clear publishing system for editors and a compact Laravel foundation for developers.',
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'Home',
            'title' => 'PagibleAI CMS for Laravel',
            'path' => '',
            'tag' => 'root',
            'theme' => $this->theme,
            'status' => 1,
            'cache' => 5,
            'editor' => 'demo',
            'config' => $config,
            'meta' => $meta,
            'content' => $content,
        ] );

        $version = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => [
                'name' => 'Home',
                'title' => 'PagibleAI CMS for Laravel',
                'path' => '',
                'tag' => 'root',
                'domain' => '',
                'theme' => $this->theme,
                'status' => 1,
                'cache' => 5,
            ],
            'aux' => [
                'config' => $config,
                'meta' => $meta,
                'content' => $content,
            ],
            'editor' => 'demo',
        ] );

        $version->files()->attach( array_unique( array_merge( [$fileId], $this->ids( $config ), $this->ids( $content ), $this->ids( $meta ) ) ) );
        $version->elements()->attach( $elementId );
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Returns file IDs referenced anywhere in the given data.
     *
     * @param mixed $value Content or meta data
     * @return array<int, string> File IDs
     */
    protected function ids( mixed $value ) : array
    {
        $ids = [];

        if( is_array( $value ) )
        {
            if( ( $value['type'] ?? null ) === 'file' && is_string( $value['id'] ?? null )
                && !isset( $value['data'] ) && !isset( $value['group'] )
            ) {
                $ids[] = $value['id'];
            }

            foreach( $value as $item ) {
                $ids = array_merge( $ids, $this->ids( $item ) );
            }
        }

        return $ids;
    }


    /**
     * Returns the file ID for a curated demo photo.
     *
     * @param string $key Photo key from self::PHOTOS
     * @return string File ID
     */
    protected function img( string $key ) : string
    {
        [$photo, $name, $desc] = self::PHOTOS[$key];
        return $this->image( $photo, $name, $desc );
    }


    /**
     * Creates the minified PagibleAI SVG logo and returns its file ID.
     *
     * @return string File ID
     */
    protected function logoFile() : string
    {
        if( !isset( $this->logoFile ) )
        {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="420" height="120" viewBox="0 0 11112.5 3175" style="clip-rule:evenodd;fill-rule:evenodd;image-rendering:optimizeQuality;shape-rendering:geometricPrecision;text-rendering:geometricPrecision"><defs><linearGradient id="a" gradientUnits="userSpaceOnUse" x1="7624.4102" y1="8678.8799" x2="7523.8501" y2="8990.6797"><stop offset="0" stop-color="#BC67EA"/><stop offset="1" stop-color="#01ACFF"/></linearGradient><linearGradient id="b" gradientUnits="userSpaceOnUse" x1="12402.1" y1="8678.8799" x2="12502.7" y2="8990.6797"><stop offset="0" stop-color="#BC67EA"/><stop offset="1" stop-color="#01ACFF"/></linearGradient><linearGradient id="c" gradientUnits="userSpaceOnUse" x1="5034.1401" y1="7855.6602" x2="4157.3999" y2="9077.6504" gradientTransform="translate(-2909.4269,-6840.6666)"><stop offset="0" stop-color="#01ACFF"/><stop offset="1" stop-color="#BC67EA"/></linearGradient><linearGradient id="d" gradientUnits="userSpaceOnUse" x1="13170.5" y1="7665.9702" x2="12901.8" y2="8223.3398" gradientTransform="translate(-2909.4269,-6840.6666)"><stop offset="0" stop-color="#01ACFF"/><stop offset="1" stop-color="#007DBC"/></linearGradient></defs><polygon points="11250,8813 11250,8857 13655,8857 13655,8813" fill="url(#b)" transform="translate(-2909.4269,-6840.6666)"/><path d="m 1109.5731,2120.3334 h 1037 l 871,-872 -871,-871 H 354.57306 l 300,300 37,37 h 527.00004 c 38,-96 182,-69 182,34 0,104 -144,130 -182,34 h -153 -306.00004 l 129,129 H 1925.5731 l 338,337 -338,338 h -283 l 338,-338 -138,-137 h -181 l 137,137 c -158,159 -317,318 -476,477 h 13 633 l 458,-459 c -15,-34 -12,-71 20,-104 33,-32 96,-36 133,0 37,37 32,101 0,133 -32,33 -70,35 -104,20 l -153,153 -257,257 h 1 l -68,68 h -671 c -14,35 -42,60 -88,60 -46,0 -94,-42 -94,-94 1,-70 74,-112 133,-87 l 424,-423 -138,-138 h -198 -97 -181 l 137,137 -871.00004,872 v 181 l 196,-196 203,-202 c -41,-95 80,-178 153,-105 73,73 -10,194 -105,153 l -447,447 v 476 z m 286,-845 c 0,19 15,34 34,34 19,0 34,-15 34,-34 0,-19 -15,-34 -34,-34 -19,0 -34,15 -34,34 z m 41,-94 v 0 c 21,1 41,10 59,28 73,73 -9,193 -104,152 l -259,259 v 0 l -78,79 v 117 177 l -376.00004,375 -64,65 c 41,94 -79,177 -153,104 -72,-73 10,-194 105,-153 l 419,-419 v -295 l 358.00004,-357 c -18,-42 -5,-82 22,-106 v 0 l 2,-2 v 0 0 c 20,-17 43,-26 69,-24 z m -197,544 c 19,0 34,15 34,34 0,18 -15,34 -34,34 -19,0 -34,-16 -34,-34 0,-19 15,-34 34,-34 z m 199,208 c 19,0 34,15 34,34 0,19 -15,34 -34,34 -19,0 -34,-15 -34,-34 0,-19 15,-34 34,-34 z m -910.00004,503 c 19,0 34,16 34,34 0,19 -15,34 -34,34 -19,0 -34,-15 -34,-34 0,-18 15,-34 34,-34 z m 311,-605 c 19,0 34,15 34,34 0,19 -15,34 -34,34 -19,0 -34,-15 -34,-34 0,-19 15,-34 34,-34 z m 1674.00004,-636 c 18,0 34,15 34,34 0,19 -16,34 -34,34 -19,0 -35,-15 -35,-34 0,-19 16,-34 35,-34 z m -93,-348 c 19,0 34,15 34,34 0,19 -15,34 -34,34 -19,0 -34,-15 -34,-34 0,-19 15,-34 34,-34 z m -1114,-125 c 18,0 34,15 34,34 0,19 -16,34 -34,34 -19,0 -34,-15 -34,-34 0,-19 15,-34 34,-34 z m 302,-216 c 19,0 35,15 35,34 0,19 -16,34 -35,34 -18,0 -34,-15 -34,-34 0,-19 16,-34 34,-34 z m -840.00004,0 c 19,0 34,15 34,34 0,19 -15,34 -34,34 -19,0 -34,-15 -34,-34 0,-19 15,-34 34,-34 z m 798.00004,276 -208,-208 H 854.57306 c -39,100 -182,66 -182,-34 1,-101 141,-133 182,-34 h 167.00004 365 l 130,130 78,78 h 413 l 295,295 h 295 l 239,239 -419,419 -165,165 -116,116 -53,53 h -14 -83 -13 -447 c -14,35 -42,60 -88,60 -46,0 -94,-42 -94,-94 0,-52 48,-94 94,-94 46,0 74,25 88,60 h 447 82 l 265,-266 419,-419 -171,-172 h -108 -186 l -296,-294 z m 498,-208 h -185 -180 c -14,35 -42,60 -87,60 -128,-1 -132,-187 0,-188 45,0 73,25 87,60 h 394 l 100,100 189,189 c 101,-44 177,79 104,152 -75,76 -197,-3 -152,-104 z" fill="url(#c)"/><path d="m 4116.5731,914.3334 c 0,-79 -24,-141 -72,-185 -48,-45 -110,-67 -186,-67 h -397 v 72 h 84 223 59 c 138,0 205,67 205,180 0,59 -18,104 -54,135 -35,31 -81,46 -138,46 h -72 -223 -84 v 450 h 84 v -378 h 313 c 76,0 138,-23 186,-67 48,-45 72,-106 72,-186 z m 4496,-252 v 72 h 84 492 32 v -72 z m 0,392 v 491 h 614 v -71 h -530 v -348 h 492 v -72 h -492 z m -839,-392 v 883 h 579 v -71 h -495 v -812 z m -320,632 c -4,-116 -68,-199 -187,-215 v -3 c 97,-23 154,-106 154,-199 0,-159 -130,-215 -277,-215 h -380 v 36 36 315 496 h 84 296 c 198,0 310,-86 310,-251 z m -606,-560 h 147 141 c 133,0 201,53 201,166 -4,108 -103,149 -195,149 h -147 -147 z m 522,558 c 0,123 -78,182 -226,182 h -296 v -353 h 289 c 149,0 233,57 233,171 z m -1069,-630 v 883 h 84 v -883 z m -736,-18 c -263,0 -420,207 -420,460 0,256 151,460 417,460 140,0 250,-61 306,-180 h 2 l 11,161 h 61 v -449 h -375 v 72 h 301 c 0,193 -120,324 -306,324 -227,0 -333,-190 -333,-388 0,-216 118,-389 336,-389 141,0 247,81 277,188 h 85 c -43,-165 -186,-259 -362,-259 z m -594,901 -347,-883 h -94 l -355,883 h 90 c 179,-460 111,-288 308,-799 v 0 c 201,530 126,338 307,799 z" fill="#fff"/><path d="m 9931.5731,843.3334 c -72,231 -146,461 -215,693 l -3,9 h -220 l 297,-883 h 284.9999 l 292,883 h -223 l -3,-9 c -69,-232 -140,-462 -209.9999,-693 z m 612.9999,-181 h 215 v 883 h -215 z" fill="url(#d)"/><path d="m 6363.5731,1742.3334 c -151,0 -235,118 -235,253 0,143 91,251 234,251 125,0 193,-79 206,-180 h -82 c -11,63 -53,108 -124,108 -105,0 -151,-89 -151,-179 0,-96 50,-182 151,-182 66,0 106,36 119,81 h 82 c -16,-95 -93,-152 -200,-152 z m 1020,492 v -481 h -122 l -129,375 h -1 l -133,-375 h -123 v 481 h 80 v -385 h 1 l 137,385 h 73 l 136,-387 h 2 v 387 z m 696,-137 c 0,-70 -45,-111 -128,-133 l -114,-30 c -33,-9 -56,-22 -56,-57 0,-47 46,-68 97,-68 59,0 97,29 101,71 h 82 c -2,-95 -87,-138 -180,-138 -99,0 -181,49 -181,141 0,66 34,104 106,123 l 104,27 c 60,16 87,37 87,71 0,51 -50,74 -112,74 -65,0 -118,-27 -118,-85 v -3 h -83 v 5 c 0,97 86,151 196,151 104,0 199,-44 199,-149 z" fill="#fff"/><polygon points="8777,8813 8777,8857 6371,8857 6371,8813" fill="url(#a)" transform="translate(-2909.4269,-6840.6666)"/></svg>';

            $disk = Storage::disk( config( 'cms.disk', 'public' ) );
            $path = rtrim( 'cms/' . $this->tenant, '/' ) . '/pagible-logo.svg';

            if( !$disk->put( $path, $svg ) ) {
                throw new \Aimeos\Cms\Exception( sprintf( 'Unable to store logo "%s"', $path ) );
            }

            $data = [
                'mime' => 'image/svg+xml',
                'lang' => 'en',
                'name' => 'PagibleAI CMS logo',
                'path' => $path,
                'previews' => ['500' => $path],
                'description' => ['en' => 'PagibleAI CMS wordmark with a blue and purple circuit symbol'],
            ];

            $file = File::forceCreate( $data + ['editor' => 'demo'] );
            $version = $file->versions()->forceCreate( [
                'lang' => 'en',
                'data' => $data,
                'editor' => 'demo',
            ] );

            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            $file->publish( $version );
            $this->logoFile = (string) $file->refresh()->id;
        }

        return $this->logoFile;
    }


    /**
     * Creates a demo page below the given parent and returns it.
     *
     * @param array<string, mixed> $data Page attributes
     * @param array<int, array<string, mixed>> $content Content elements
     * @param Page $parent Parent page to append to
     * @param array<int, string> $fileIds Additional file IDs to attach
     * @param array<string, array<string, mixed>|object> $meta Meta entries keyed by type
     * @return Page Created page
     */
    protected function page( array $data, array $content, Page $parent, array $fileIds = [], array $meta = [] ) : Page
    {
        $elementId = $this->element();
        $fileId = $this->file();
        $description = self::DESCRIPTIONS[$data['path'] ?? ''] ?? $data['title'] ?? '';

        $meta = $data['meta'] ?? $meta ?: [
            'meta-tags' => Validation::entry( 'meta-tags', [
                'description' => $description,
                'keywords' => 'PagibleAI CMS, Laravel CMS, structured content, publishing',
            ], 'meta' ),
            'social-media' => Validation::entry( 'social-media', [
                'title' => $data['title'] ?? '',
                'description' => $description,
                'file' => ['id' => $fileId, 'type' => 'file'],
            ], 'meta' ),
        ];

        $content[] = ['id' => Utils::uid(), 'type' => 'heading', 'group' => 'footer', 'data' => ['level' => 2, 'title' => 'PagibleAI CMS']];
        $content[] = ['type' => 'reference', 'refid' => $elementId, 'group' => 'footer'];

        $page = Page::forceCreate( $data + [
            'theme' => $this->theme,
            'editor' => 'demo',
            'meta' => $meta,
            'content' => $content,
        ] );
        $page->appendToNode( $parent )->save();

        $version = $page->versions()->forceCreate( [
            'lang' => $data['lang'] ?? 'en',
            'data' => array_diff_key( $data, ['content' => 1, 'meta' => 1, 'id' => 1] ) + [
                'domain' => '',
                'theme' => $this->theme,
            ],
            'aux' => ['meta' => $meta, 'content' => $content],
            'editor' => 'demo',
        ] );

        $version->elements()->attach( $elementId );
        $version->files()->attach( array_unique( array_merge( [$fileId], $fileIds, $this->ids( $content ), $this->ids( $meta ) ) ) );

        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        $page->publish( $version );

        return $page;
    }


    /**
     * Builds the Pagible theme demo page tree.
     */
    protected function pages() : void
    {
        $blogId = (string) Str::uuid7();
        $home = $this->home( $blogId );

        $this->addDocs( $home )
            ->addBlog( $home, $blogId );
    }
}
