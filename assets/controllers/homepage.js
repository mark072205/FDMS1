import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        console.log('Homepage controller connected!');
        
        // Prevent back button after logout
        this.preventBackButton();
    }

    preventBackButton() {
        window.history.forward();
        window.onload = () => window.history.forward();
        window.onpageshow = function(evt) { 
            if (evt.persisted) window.history.forward(); 
        };
    }
}
