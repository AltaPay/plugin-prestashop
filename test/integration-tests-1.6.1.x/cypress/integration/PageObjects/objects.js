require('cypress-xpath')

class Order

{
    clrcookies(){
        cy.clearCookies()
    }
    visit()
    {
        cy.fixture('config').then((url)=>{
        cy.visit(url.shopURL) 
  
            })    
    }
  
    addproduct(discount_type=''){
        cy.get('#blocknewproducts > :nth-child(2) > .product-container > .right-block > .button-container > .ajax_add_to_cart_button > span').click() 
        cy.get('.button-medium > span').click()
        cy.get('.cart_navigation > .button > span').click()
        //Guest checkout 1.6.X
        cy.get('#guest_email').type('demo@example.com')
        cy.get('#firstname').type('Testperson-dk')
        cy.get('#lastname').type('Testperson-dk')
        cy.get('#address1').type('Sæffleberggate 56,1 mf')
        cy.get('#postcode').type('6800')
        cy.get('#city').type('Varde')
        cy.get('#id_country').select('Denmark')
        cy.get('#phone_mobile').type('20123456')
        cy.get('.cart_navigation > .button > span').click()
        if(discount_type != ""){
            cy.get('#discount_name').type(discount_type) 
            cy.get('fieldset > .button > span').click()
        } 
        cy.get('.cart_navigation > .button > span').click().wait(2000)
        cy.get('.cart_navigation > .button > span').click().wait(2000)
        cy.get('label').click()
        cy.get('.cart_navigation > .button > span').click()

    }

    cc_payment(CC_TERMINAL_NAME){        
        cy.contains(CC_TERMINAL_NAME).click({force: true})

        cy.get('[id=creditCardNumberInput]').type('4111111111111111')
        cy.get('#emonth').type('01')
        cy.get('#eyear').type('2023')
        cy.get('#cvcInput').type('123')
        cy.get('#cardholderNameInput').type('testname')
        cy.get('#pensioCreditCardPaymentSubmitButton').click().wait(4000)

    
    }

        klarna_payment(KLARNA_DKK_TERMINAL_NAME){
            cy.contains(KLARNA_DKK_TERMINAL_NAME).click({force: true})

        cy.get('[id=submitbutton]').click().wait(3000)
        cy.get('[id=klarna-pay-later-fullscreen]').wait(5000).then(function($iFrame){
            const mobileNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-phone-number]')
            cy.wrap(mobileNum).type('(452) 012-3456')
            const personalNum = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-national-identification-number]')
            cy.wrap(personalNum).type('1012201234')
            const submit = $iFrame.contents().find('[id=invoice_kp-purchase-approval-form-continue-button]')
            cy.wrap(submit).click().wait(4000)
            
    })    
    }

    admin()
    {
            cy.clearCookies()
            cy.fixture('config').then((admin)=>{
            cy.visit(admin.adminURL)
            cy.get('#email').type(admin.adminUsername)
            cy.get('#passwd').type(admin.adminPass).wait(2000)
            cy.get('.ladda-label').click()
            cy.visit(admin.adminURL).wait(3000)
            })

    }

    capture(){

        // 1.6.X
        cy.get('#maintab-AdminParentOrders > .title').click()
        //Exception handle
        Cypress.on('uncaught:exception', (err, runnable) => {
            return false
        })

        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        //Capture
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-capture]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()   
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'captured')
    }
    refund(){
        //Refund
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get(':nth-child(2) > :nth-child(10) > .form-control').type('3').click()
            
        })
            
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'refunded')
    }   

    addpartial_product(){
        cy.get('#blocknewproducts > .last-line.last-item-of-tablet-line > .product-container > .right-block > .button-container > .ajax_add_to_cart_button > span').click()
        cy.get('.continue > span').click()
    }
    partial_capture(){
        // 1.6.X
        cy.get('#maintab-AdminParentOrders > .title').click()
        //Exception handle
        Cypress.on('uncaught:exception', (err, runnable) => {
            return false
        })

        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        cy.get(':nth-child(2) > :nth-child(10) > .form-control').clear().type("1").click()
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-capture]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()   
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'captured')
    }
    partial_refund(){
        // 1.6.X
        cy.get('#maintab-AdminParentOrders > .title').click()
        //Exception handle
        Cypress.on('uncaught:exception', (err, runnable) => {
            return false
        })

        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        cy.get(':nth-child(2) > :nth-child(10) > .form-control').clear().type("1").click()
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()   
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'refunded')
    }
    release_payment(){
         // 1.6.X
         cy.get('#maintab-AdminParentOrders > .title').click()
         //Exception handle
         Cypress.on('uncaught:exception', (err, runnable) => {
             return false
         })
        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-release]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click() 
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'released')
    }
    change_currency_to_EUR_for_iDEAL(){
        cy.get('#maintab-AdminParentLocalization > .title').trigger('mouseover')
        cy.get('#subtab-AdminCurrencies').click()
        cy.get('.edit').click()
        cy.get('#name').clear().type('Euro')
        cy.get('#iso_code').clear().type('EUR')
        cy.get('#iso_code_num').clear().type('978')
        cy.get('#sign').clear().type('€')
        cy.get('#conversion_rate').clear().type('1')
        cy.get('#currency_form_submit_btn').click()       
    }

    re_save_EUR_currency_config(){
        // Re-save EUR Terminal Config
        cy.get('#maintab-AdminParentModules > .title').click()
        cy.get('#moduleQuicksearch').type('Alta').wait(1000)
        cy.get(':nth-child(20) > .actions > .btn-group-action > .btn-group > a.btn').click()
        cy.fixture('config').then((admin) => {
        cy.contains(admin.iDEAL_EUR_TERMINAL).click()
        })
        cy.get('#altapay_terminals_form_submit_btn').click()
    }

    re_save_DKK_currency_config(){
        cy.get('#maintab-AdminParentModules > .title').click()
        cy.get('#moduleQuicksearch').type('Alta')
        cy.get(':nth-child(20) > .actions > .btn-group-action > .btn-group > a.btn').click()
        cy.fixture('config').then((admin) => {
        cy.contains(admin.CC_TERMINAL_NAME).click()
        })
        cy.get('#altapay_terminals_form_submit_btn').click()
    }

    ideal_payment(iDEAL_EUR_TERMINAL){        
        cy.contains(iDEAL_EUR_TERMINAL).click({force: true})
        cy.get('#idealIssuer').select('AltaPay test issuer 1')
        cy.get('#pensioPaymentIdealSubmitButton').click()
        cy.get('[type="text"]').type('shahbaz.anjum123-facilitator@gmail.com')
        cy.get('[type="password"]').type('Altapay@12345')
        cy.get('#SignInButton').click()
        cy.get(':nth-child(3) > #successSubmit').click().wait(1000)
    }
    ideal_refund(){
        cy.get('#maintab-AdminParentOrders > .title').click()
        cy.get('tbody > :nth-child(1) > .fixed-width-xs').click().wait(1000)
         cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get(':nth-child(2) > :nth-child(10) > .form-control').type('3').click()
            
        })
            
        cy.get('[id=transactionOptions]').then(function($iFrame){
            const capture = $iFrame.contents().find('[id=btn-refund]')
            cy.wrap(capture).click({force: true})
            cy.get('#popup_ok').click()
            cy.get('#popup_ok').click()
        })
        cy.get('#altapay > div > div > div.row.panel-body > div:nth-child(4) > div:nth-child(1) > div > div > table > tbody > tr:nth-child(1) > td:nth-child(2)').should('have.text', 'bank_payment_refunded')
    }

    change_currency_to_DKK(){
        cy.get('#maintab-AdminParentLocalization > .title').trigger('mouseover')
        cy.get('#subtab-AdminCurrencies').click()
        cy.get('.edit').click()
        cy.get('#name').clear().type('Danish Krone')
        cy.get('#iso_code').clear().type('DKK')
        cy.get('#iso_code_num').clear().type('208')
        cy.get('#sign').clear().type('Kr')
        cy.get('#conversion_rate').clear().type('1')
        cy.get('#currency_form_submit_btn').click()
        
    }
    clear_cache(){
        cy.wait(1000)
        cy.get('#maintab-AdminTools > .title').trigger('mouseover')
        cy.get('#subtab-AdminPerformance').click()
        cy.get('#page-header-desc-configuration-clear_cache').click().wait(2000)
    }

    //Dicounts
    create_fixed_discount(){
        cy.get('#maintab-AdminPriceRule > .title').click()
        cy.get('.label-tooltip > .process-icon-new').click()
        cy.get('#name_1').clear().type('Discount_F')
        cy.get('#code').clear().type('fixed')
        cy.get('#cart_rule_link_actions').click()
        cy.get('#apply_discount_amount').click()
        cy.get('#reduction_amount').clear().type('12')
        cy.get('#cart_rule_link_conditions').click()
        cy.get(':nth-child(4) > .col-lg-9 > .form-control').clear().type('9999')
        cy.get(':nth-child(5) > .col-lg-9 > .form-control').clear().type('9999')
        cy.get('#desc-cart_rule-save').click()
        cy.get('body').then(($body) => {
            if ($body.text().includes('This cart rule code is already used')) {
                cy.get('#desc-cart_rule-cancel').click()
            }
        })
        cy.get('.label-tooltip > .process-icon-new').click()
        cy.get('#name_1').clear().type('Discount_%')
        cy.get('#code').clear().type('percentage')
        cy.get('#cart_rule_link_actions').click()
        cy.get('#apply_discount_percent').click()
        cy.get('#reduction_percent').clear().type('7')
        cy.get('#cart_rule_link_conditions').click()
        cy.get(':nth-child(4) > .col-lg-9 > .form-control').clear().type('9999')
        cy.get(':nth-child(5) > .col-lg-9 > .form-control').clear().type('9999')
        cy.get('#desc-cart_rule-save').click()
        cy.get('body').then(($body) => {
            if ($body.text().includes('This cart rule code is already used')) {
                cy.get('#desc-cart_rule-cancel').click()
            }
        })
    }
 
}
export default Order