
import { __ } from '@wordpress/i18n';

const CardFields = () => {
    return (
        <div className="cbo-card-fields">
        <div className="cbo-card-fields__group">
            <label>Número de tarjeta</label>
            <input type="text" />
        </div>
        <div className="cbo-card-fields__row">
            <div className="cbo-card-fields__group">
            <label>Fecha de expiración</label>
            <input type="text" />
            </div>
            <div className="cbo-card-fields__group">
            <label>CVC</label>
            <input type="text" />
            </div>
        </div>
</div>
    );
};

export default CardFields;
