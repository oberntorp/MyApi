<?php
	class PagesHelper
	{
		public function DidParagraphsUpdate($nbrParagraphs, $paragraphUpdateResult)
		{
			return $nbrParagraphs == $paragraphUpdateResult;
		}
	}
?>