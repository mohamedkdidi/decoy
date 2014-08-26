<?php namespace Bkwld\Decoy\Models;

// Dependencies
use Config;
use Request;
use HtmlObject\Element;

/**
 * Stores the status of an encoding job and the converted outputs.
 * It was designed to handle the conversion of video files to
 * HTML5 formats with Zencoder but should be abstract enough to 
 * support other types of encodings.
 */
class Encoding extends Base {

	/**
	 * Comprehensive list of states
	 */
	static private $states = array(
		'error',      // Any type of error
		'pending',    // No response from encoder yet
		'queued',     // The encoder API has been hit
		'processing', // Encoding has started
		'complete',   // Encode is finished
		'cancelled',  // The user has canceled the encode
	);

	/**
	 * Polymorphic relationship definition
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\MorphTo
	 */
	public function encodable() { return $this->morphTo(); }

	/**
	 * Set default fields and delete any old encodings for the same source.
	 *
	 * @return void 
	 */
	public function onCreating() {

		// Delete any other encoding jobs for the same parent and field
		self::where('encodable_type', '=', $this->encodable_type)
			->where('encodable_id', '=', $this->encodable_id)
			->where('encodable_attribute', '=', $this->encodable_attribute)
			->delete();

		// Default values
		$this->status = 'pending';
		
	}

	/**
	 * Once the model is created, try to encode it.  This is done during
	 * the created callback so we can we call save() on the record without
	 * triggering an infitie loop like can happen if one tries to save while
	 * saving
	 *
	 * @return void 
	 */
	public function onCreated() {
		static::encoder()->encode($this->source(), $this);
	}

	/**
	 * Make an instance of the encoding provider
	 *
	 * @param array $input Input::get()
	 * @return mixed Reponse to the API
	 */
	static public function notify($input) {
		return static::encoder()->handleNotification($input);
	}

	/**
	 * Get an instance of the configured encoding provider
	 *
	 * @return Bkwld\Decoy\Input\EncodingProviders\EncodingProvider
	 */
	static public function encoder() {
		$class = Config::get('decoy::encode.provider');
		return new $class;
	}

	/**
	 * Get the source video for the encode
	 *
	 * @return string The path to the video relative to the 
	 *                document root
	 */
	public function source() {
		$val = $this->encodable->{$this->encodable_attribute};
		if (preg_match('#^http#', $val)) return $val;
		else return Request::root().$val;
	}

	/**
	 * Store a record of the encoding job
	 *
	 * @param string $job_id A unique id generated by the service
	 * @param mixed $outputs An optional assoc array where the keys are 
	 *                       labels for the outputs and the values are 
	 *                       absolute paths of where the output will be saved
	 * @return void 
	 */
	public function storeJob($job_id, $outputs = null) {
		$this->status = 'queued';
		$this->job_id = $job_id;
		$this->outputs = json_encode($outputs);
		$this->save();
	}

	/**
	 * Update the status of the encode
	 *
	 * @param  string status 
	 * @param  string $message
	 * @return void  
	 */
	public function status($status, $message = null) {
		if (!in_array($status, static::$states)) throw new Exception('Unknown state: '.$status);
		$this->status = $status;
		$this->message = $message;
		$this->save();
	}

	/**
	 * Generate an HTML5 video tag via Former's HtmlObject for the outputs
	 *
	 * @return HtmlObject\Element
	 */
	public function getTagAttribute() {

		// Require sources
		if (!$sources = $this->outputs) return;

		// Start the tag
		$tag = Element::video();

		// Loop through the outputs and add them as sources
		foreach(json_decode($sources) as $type => $src) {
			$tag->appendChild(Element::source()
				->type('video/'.$type)
				->src($src)
			);
		}

		// Return the tag
		return $tag;
	}

}