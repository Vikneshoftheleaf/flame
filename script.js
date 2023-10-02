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


app.post('/submit', (req, res) => {
    const formData = {
        person1: req.body.name1,
        person2: req.body.name2,
        mode: req.body.mode
    };

    // Load existing data from the JSON file
    let data = [];
    try {
        const existingData = fs.readFileSync('data.json', 'utf-8');
        data = JSON.parse(existingData);
    } catch (error) {
        console.error('Error reading data from JSON file:', error);
    }

    // Add the new form data
    data.push(formData);

    // Save the updated data to the JSON file
    fs.writeFile('data.json', JSON.stringify(data, null, 2), (error) => {
        if (error) {
            console.error('Error writing data to JSON file:', error);
            res.status(500).send('Error saving data');
        } 
    });
});

app.listen(port,()=>{
    console.log(`Listeneing at ${port}`)
})

