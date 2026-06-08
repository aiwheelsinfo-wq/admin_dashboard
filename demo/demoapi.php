<!DOCTYPE html>
<html>
<head>
  <title>Fetch API Example</title>
</head>
<body>
  <h2>Users</h2>
  <ul id="user-list"></ul>
 <button type="submit" onclick="show()">Click Me, I’ll show you the vendor name.</button>
<div id="vendorName"></div>

  <script>
    // async function fetchdatademo(){
    //     try {
    //         const responce= await fetch("https://jsonplaceholder.typicode.com/users");
    //         const data= await responce.json();
    //         console.log(data);
    //     }
    //     catch(error){
    //         console.error('Error fetching data',error);
    //     }
    // }
    // fetchdatademo();
    
    // async function secondfunc(){
    //     try{
    //     let responce= await fetch("https://jsonplaceholder.typicode.com/users");
    //     let data= await responce.json();
    //     // console.log(data);
    //     const v=document.getElementById("user-list");
    //     data.forEach(user => {
    //         const li = document.createElement('li');
    //         li.textContent = `${user.name} - ${user.email}`;
    //         v.appendChild(li);

    //     });
    //     }
    //     catch(error){
    //         console.log("print this:",error)
    //     }
    // }
    // secondfunc();
    
    
//     async function createPost() {
//   try {
//     const response = await fetch("https://jsonplaceholder.typicode.com/posts", {
//       method: "POST",
//       headers: {
//         "Content-Type": "application/json"
//       },
//       body: JSON.stringify({
//         title: "Rbaz",
//         body: "bar",
//         userId: 9
//       })
//     });

//     const data = await response.json();
//     console.log("POST response:", data);
//     document.getElementById("user-list").textContent = `Post created with ID: ${data.userId}`;
//     // document.getElementById("user-list").textContent = `Post created with ID: ${data.title}`;
//   } catch (error) {
//     console.error("POST failed:", error);
//   }
// }

// createPost();



// fetch('https://agnicarrental.com/admin2025/Oluber/get_vendor_name.php?vendor_id=9372696409')
//     .then(response => response.json())
//     .then(data => console.log(data))
//     .catch(error => console.log(error));


let data = null; 

  async function func() {
    let v = await fetch('https://agnicarrental.com/admin2025/Oluber/get_vendor_name.php?vendor_id=9372696409');
    data = await v.json();
  }

  function show() {
    document.write(data.name);
  }

  func(); 
  






















  </script>
</body>
</html>
