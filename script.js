const express = require('express');
const app = express();
const bodyParser = require('body-parser');
const fs = require('fs');

const port = 8080;

app.use(bodyParser.urlencoded({ extended: true }));

app.use(express.static('public'))

app.get('/',(req,res)=>{
    res.sendFile(__dirname +"/index.html")
})

app.get('/calc',(req,res)=>{
    res.sendFile(__dirname +"/calc.html")
})

app.get('/coming',(req,res)=>{
    res.send("coming soon!")
})
app.get('/flame',(req,res)=>{
    res.sendFile(__dirname +"/flame.html")
})


app.listen(port,()=>{
    console.log(`Listeneing at ${port}`)
})

