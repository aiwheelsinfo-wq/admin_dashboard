<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Basic Web Page</title>
  <style>
    body {
      background-color: #f5f5f5;
      font-family: Arial, sans-serif;
      text-align: center;
      padding: 30px;
    }

    h1 {
      color: #333;
    }

    button {
      padding: 10px 20px;
      font-size: 16px;
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }

    button:hover {
      background-color: #b4d1ba;
    }

    #output {
      margin-top: 20px;
      font-size: 18px;
      color: #555;
    }
  </style>
</head>
<body onload="pageLoaded()">

  <h1>My First HTML + CSS + JS Page</h1>
  <p>This is a paragraph with some <strong>bold</strong> text.</p>
  
  
<button onclick="sayHello()">Click Me</button>
<p onmouseover="changeText(this)">Hover over me!</p>
<p onmouseout="resetText(this)">Move your mouse away</p>

<select onchange="showValue(this)">
  <option value="Apple">Apple</option>
  <option value="Banana">Banana</option>
</select>
<p id="fruit"></p>


<label>Full Name:</label>
<input type="text" id="name" placeholder="Enter your full name....">
<button onclick="func()">Submit</button>
<p id="pname"></p>


<input type="text" onkeyup="showTyping(this.value)" placeholder="Type something">
<p id="typingText"></p>

<form onsubmit="return validateForm()">
  <input type="text" id="name1" placeholder="Enter your name">
  <input type="submit" value="Submit">
</form>
<input type="text" onfocus="highlight(this)" onblur="unhighlight(this)" placeholder="Focus me">

<h6>Hello AGNI</h6>

<h6 id="head">Hello i am here</h6>
<h1 class="jk">Hello World</h1>

<p class="agni">Agni Car Rental</p>

<button id="v2">Check Event</button>
<h1 class="v3"><strong>HELLO I AM STRONG</strong></h1>

<h1>Agni</h1>

<div class="item">Item 1</div>
<div class="item">Item 2</div>
<div class="item">Item 3</div>

<h1>User List</h1>
  <div id="userList">Loading users...</div>

<script>

  function sayHello() {
    alert("Hello!");
  }
  
  function changeText(element) {
    element.innerHTML = "Mouse is over!";
  }
  
  function resetText(element) {
    element.innerHTML = "Mouse has left!";
  }
  
  function showValue(element) {
    document.getElementById("fruit").innerText = "You selected: " + element.value;
  }
  
  function func(){
      let fname=document.getElementById("name").value;
      document.getElementById("pname").innerText="Welcome: "+ fname;
  }
  
  function showTyping(text) {
    document.getElementById("typingText").innerText = "You typed: " + text;
  }
  
  function pageLoaded() {
    alert("Page is fully loaded!");
  }
  
  function validateForm() {
        let name = document.getElementById("name1").value;
        if (name === "") {
          alert("Name cannot be empty");
          return false; // Prevent form submission
        }
        return true;
      }
          
    function highlight(el) {
        el.style.background = "yellow";
      }
    
    function unhighlight(el) {
        el.style.background = "red";
      }
      
    var selectthetag=document.querySelector("h6");
    console.log(selectthetag);
    
    let selectid = document.getElementById("head");
    console.log(selectid);
    
    const selectclass = document.getElementsByClassName("jk");
    console.log(selectclass);
    console.log(selectclass[0].innerText);
    
    let A=document.querySelector(".agni");
    A.style.color = "red";

    let B=document.querySelector("#v2");
    let C=document.getElementsByClassName("v3")[0];
    // console.log(C);
    B.addEventListener("click",function(){
    C.style.backgroundColor="blue";
    })
    
    // let items = document.getElementsByClassName("item");

    // console.log(items);           // HTMLCollection(3) [div.item, div.item, div.item]
    // console.log(items[0].innerText); // "Item 1"
    
    

    // let items = document.querySelectorAll(".item");

    // for (let i = 0; i < items.length; i++) {
    //       items[i].style.color = "blue";
    //     }


    let variable=document.getElementsByClassName("item");
    console.log(variable);
    console.log(variable[0]);

async function loadUsers() {
  try {
    // Fetch data from the API
    const response = await fetch('https://jsonplaceholder.typicode.com/users');

    // Check if the response is OK
    if (!response.ok) {
      throw new Error('Server error!');
    }

    // Convert response to JSON
    const data = await response.json();

    // Get the container from the page
    const userList = document.getElementById('userList');
    userList.innerHTML = ''; // Clear old content

    // Loop through users and add them to the page
    data.forEach(user => {
      const userDiv = document.createElement('div');
      userDiv.innerHTML = `<strong>${user.name}</strong> (${user.email})`;
      userList.appendChild(userDiv);
    });
  } catch (error) {
    // If something goes wrong
    document.getElementById('userList').innerText = 'Failed to load users.';
    console.error('Error:', error);
  }
}

// Call the function to load users
loadUsers();

const var1=document.createElement('p');
const var2=document.createTextNode("This is my new Dom element.");

var1.appendChild(var2);
document.body.appendChild(var2);


</script>


</body>
</html>
