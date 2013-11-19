<?php
class HTMLTextField extends HTMLFormField {
	function getSize() {
		return isset( $this->mParams[ 'size' ] ) ? $this->mParams[ 'size' ] : 45;
	}

	function getInputHTML( $value ) {
		$attribs = array(
				'id' => $this->mID,
				'name' => $this->mName,
				'size' => $this->getSize(),
				'value' => $value,
			) + $this->getTooltipAndAccessKey();

		if ( $this->mClass !== '' ) {
			$attribs[ 'class' ] = $this->mClass;
		}

		if ( ! empty( $this->mParams[ 'disabled' ] ) ) {
			$attribs[ 'disabled' ] = 'disabled';
		}

		# TODO: Enforce pattern, step, required, readonly on the server side as
		# well
		$allowedParams = array(
			'min',
			'max',
			'pattern',
			'title',
			'step',
			'placeholder',
			'list',
			'maxlength'
		);
		foreach ( $allowedParams as $param ) {
			if ( isset( $this->mParams[ $param ] ) ) {
				$attribs[ $param ] = $this->mParams[ $param ];
			}
		}

		foreach ( array( 'required', 'autofocus', 'multiple', 'readonly' ) as $param ) {
			if ( isset( $this->mParams[ $param ] ) ) {
				$attribs[ $param ] = '';
			}
		}

		# Implement tiny differences between some field variants
		# here, rather than creating a new class for each one which
		# is essentially just a clone of this one.
		if ( isset( $this->mParams[ 'type' ] ) ) {
			switch( $this->mParams[ 'type' ] ) {
				case 'email':
					$attribs[ 'type' ] = 'email';
					break;
				case 'int':
					$attribs[ 'type' ] = 'number';
					break;
				case 'float':
					$attribs[ 'type' ] = 'number';
					$attribs[ 'step' ] = 'any';
					break;
				# Pass through
				case 'password':
				case 'file':
					$attribs[ 'type' ] = $this->mParams[ 'type' ];
					break;
			}
		}

		return Html::element( 'input', $attribs );
	}
}