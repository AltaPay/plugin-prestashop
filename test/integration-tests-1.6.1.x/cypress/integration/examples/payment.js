import Order from '../PageObjects/objects'

describe ('Presta 1.6', function(){


    it('CC Payment', function(){

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        ord.addproduct()
        cy.fixture('config').then((admin)=>{
            if (admin.CC_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                if($a.find("a:contains('"+admin.CC_TERMINAL_NAME+"')").length){
                        ord.cc_payment(admin.CC_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                }else{
                    cy.log(admin.CC_TERMINAL_NAME + ' not found in page')
                }

                })
            
            }
            else {
                cy.log('CC_TERMINAL_NAME skipped')
            } 
        })
    })

    it('Klarna Payment', function(){

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        ord.addproduct()
        cy.fixture('config').then((admin)=>{
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                if($a.find("a:contains('"+admin.KLARNA_DKK_TERMINAL_NAME+"')").length){
                        ord.klarna_payment(admin.KLARNA_DKK_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                }else{
                    cy.log(admin.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                }

                })
            
            }
            else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            } 
        })
    })

})