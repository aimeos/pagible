@props(['page'])
<dialog id="modal-search" class="search">
	<article>
		<header>
			<form action="{{ cmsroute('cms.search', ['q' => '_term_', 'locale' => cms($page, 'lang')]) }}" toolname="search" tooldescription="{{ __('Search the website and return matching pages with their titles and links') }}" toolautosubmit>
				<input id="modal-search-input" placeholder="{{ __('Search website') }}" aria-label="{{ __('Search website') }}" name="q" minlength="{{ config('cms.theme.min-search') }}" required toolparamdescription="{{ __('Words or phrase to search for in the website content') }}">
				<button type="reset" aria-label="{{ __('Close') }}">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
						<path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/>
					</svg>
				</button>
			</form>
		</header>
		<div class="results" data-load-more="{{ __('Load more') }}" data-no-results="{{ __('No results found') }}" data-failed="{{ __('Search failed') }}"></div>
	</article>
</dialog>
