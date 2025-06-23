import { useEffect } from '@wordpress/element';

export default function ProcessPaymentHandler( props ) {
  const { eventRegistration, emitResponse } = props;

  useEffect( () => {
    const unsubscribe = eventRegistration.onPaymentProcessing( async () => {
      return {
        type: emitResponse.responseTypes.SUCCESS,
      };
    } );

    return unsubscribe;
  }, [ eventRegistration, emitResponse ] );

  return null;
}
