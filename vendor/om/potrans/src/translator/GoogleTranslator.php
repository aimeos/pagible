<?php

namespace potrans\translator;

use Gettext\Translation;
use Google\ApiCore\ApiException;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;

class GoogleTranslator extends TranslatorAbstract {
	private TranslationServiceClient $translationServiceClient;
	private string $location;
	private string $project;

	public function __construct(
		TranslationServiceClient $translationServiceClient,
		string $project,
		string $location,
	) {
		$this->translationServiceClient = $translationServiceClient;
		$this->project = $project;
		$this->location = $location;
	}

	/**
	 * @throws ApiException
	 */
	public function getTranslation(Translation $sentence): string {

		// Prepare the request
		$request = new TranslateTextRequest();
		$request->setParent(TranslationServiceClient::locationName($this->project, $this->location));
		$request->setContents([$sentence->getOriginal()]);
		$request->setTargetLanguageCode($this->to);
		$request->setSourceLanguageCode($this->from);

		// Make the API call
		$response = $this->translationServiceClient->translateText($request);

		return $response->getTranslations()[0]->getTranslatedText();
	}
}