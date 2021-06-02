import Order from '../PageObjects/objects'

describe ('Presta 1.6', function(){

    it('CC Payment', function(){

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        ord.addproduct()
        ord.cc_payment()
        ord.admin()
        ord.capture()
    })

    it('klarna', function(){

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        ord.addproduct()
        ord.klarna_payment()
        ord.admin()
        ord.capture()
    })

})